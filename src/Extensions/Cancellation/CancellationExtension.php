<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions\Cancellation;

use Cline\Forrst\Contracts\FunctionInterface;
use Cline\Forrst\Contracts\ProvidesFunctionsInterface;
use Cline\Forrst\Data\ErrorData;
use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Events\ExecutingFunction;
use Cline\Forrst\Events\FunctionExecuted;
use Cline\Forrst\Events\RequestValidated;
use Cline\Forrst\Exceptions\EmptyFieldException;
use Cline\Forrst\Exceptions\FieldExceedsMaxLengthException;
use Cline\Forrst\Exceptions\FieldOutOfRangeException;
use Cline\Forrst\Exceptions\InvalidFieldValueException;
use Cline\Forrst\Exceptions\MustBePositiveException;
use Cline\Forrst\Extensions\AbstractExtension;
use Cline\Forrst\Extensions\Cancellation\Functions\CancelFunction;
use Cline\Forrst\Extensions\ExtensionUrn;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Override;
use Throwable;

use function error_log;
use function is_string;
use function mb_strlen;
use function preg_match;

/**
 * Cancellation extension handler.
 *
 * Enables explicit request cancellation for synchronous requests. Clients
 * can include a cancellation token in the request and send a separate
 * cancel request using that token.
 *
 * Request options:
 * - token: string - Unique cancellation token
 *
 * Cancel via forrst.cancel function:
 * - arguments: {token: string}
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/specs/forrst/extensions/cancellation
 */
final class CancellationExtension extends AbstractExtension implements ProvidesFunctionsInterface
{
    /**
     * Cache prefix for cancellation tokens.
     */
    private const string CACHE_PREFIX = 'forrst:cancel:';

    /**
     * Default token TTL in seconds.
     */
    private const int DEFAULT_TTL = 300;

    /**
     * Maximum token TTL in seconds (1 hour).
     */
    private const int MAX_TTL = 3_600;

    /**
     * Create a new cancellation extension instance.
     *
     * @param int $tokenTtl Token time-to-live in seconds. Defines how long cancellation
     *                      tokens remain active in cache before expiring. Should exceed
     *                      typical request processing time to prevent premature cleanup.
     *                      Maximum allowed: 3600 seconds (1 hour).
     *
     * @throws InvalidArgumentException If TTL exceeds maximum or is negative
     */
    public function __construct(
        private int $tokenTtl = self::DEFAULT_TTL,
    ) {
        if ($tokenTtl <= 0) {
            throw MustBePositiveException::forField('tokenTtl');
        }

        if ($tokenTtl > self::MAX_TTL) {
            throw FieldOutOfRangeException::forField('tokenTtl', 1, self::MAX_TTL);
        }

        $this->tokenTtl = $tokenTtl;
    }

    /**
     * Get the functions provided by this extension.
     *
     * @return array<int, class-string<FunctionInterface>>
     */
    #[Override()]
    public function functions(): array
    {
        return [
            CancelFunction::class,
        ];
    }

    #[Override()]
    public function getUrn(): string
    {
        return ExtensionUrn::Cancellation->value;
    }

    #[Override()]
    public function getSubscribedEvents(): array
    {
        return [
            RequestValidated::class => [
                'priority' => 5,
                'method' => 'onRequestValidated',
            ],
            ExecutingFunction::class => [
                'priority' => 5,
                'method' => 'onExecutingFunction',
            ],
            FunctionExecuted::class => [
                'priority' => 5,
                'method' => 'onFunctionExecuted',
            ],
        ];
    }

    /**
     * Register cancellation token on request validation.
     *
     * Validates the cancellation token is provided and non-empty, then registers
     * it as active in cache. Returns error response if token validation fails.
     *
     * @param RequestValidated $event Request validation event with extension data
     */
    public function onRequestValidated(RequestValidated $event): void
    {
        $extension = $event->request->getExtension(ExtensionUrn::Cancellation->value);

        if (!$extension instanceof ExtensionData) {
            return;
        }

        $token = $extension->options['token'] ?? null;

        if (!is_string($token) || $token === '') {
            $event->setResponse(ResponseData::error(
                new ErrorData(
                    code: ErrorCode::InvalidArguments,
                    message: 'Cancellation token is required',
                ),
                $event->request->id,
            ));
            $event->stopPropagation();

            return;
        }

        try {
            $validToken = $this->validateToken($token);
        } catch (InvalidArgumentException $invalidArgumentException) {
            $event->setResponse(ResponseData::error(
                new ErrorData(
                    code: ErrorCode::InvalidArguments,
                    message: $invalidArgumentException->getMessage(),
                ),
                $event->request->id,
            ));
            $event->stopPropagation();

            return;
        }

        // Register the token as active (not cancelled)
        try {
            Cache::put(self::CACHE_PREFIX.$validToken, 'active', $this->tokenTtl);
        } catch (Throwable $throwable) {
            // Log the error
            error_log('Failed to register cancellation token: '.$throwable->getMessage());

            $event->setResponse(ResponseData::error(
                new ErrorData(
                    code: ErrorCode::InternalError,
                    message: 'Failed to register cancellation token',
                ),
                $event->request->id,
            ));
            $event->stopPropagation();
        }
    }

    /**
     * Check cancellation status before function execution.
     *
     * Looks up the cancellation token status in cache. If marked as cancelled,
     * stops propagation and returns CANCELLED error response. Cleans up token
     * after cancellation is detected. Uses locks to prevent race conditions.
     *
     * @param ExecutingFunction $event Function execution event with extension data
     */
    public function onExecutingFunction(ExecutingFunction $event): void
    {
        $extension = $event->extension;

        $token = $extension->options['token'] ?? null;

        if (!is_string($token) || $token === '') {
            return;
        }

        $key = self::CACHE_PREFIX.$token;
        $lock = Cache::lock($key.':lock', 1);

        try {
            $lock->block(1);

            $status = Cache::get($key);

            if ($status === 'cancelled') {
                Cache::forget($key);

                $event->setResponse(ResponseData::error(
                    new ErrorData(
                        code: ErrorCode::Cancelled,
                        message: 'Request was cancelled by client',
                    ),
                    $event->request->id,
                ));
                $event->stopPropagation();
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Clean up token after successful function execution.
     *
     * Removes the token from cache after the request completes successfully.
     * Prevents memory leaks by ensuring tokens don't accumulate in cache.
     *
     * @param FunctionExecuted $event Event containing completed request
     */
    public function onFunctionExecuted(FunctionExecuted $event): void
    {
        $extension = $event->request->getExtension(ExtensionUrn::Cancellation->value);

        if (!$extension instanceof ExtensionData) {
            return;
        }

        $token = $extension->options['token'] ?? null;

        if (!is_string($token) || $token === '') {
            return;
        }

        $this->cleanup($token);
    }

    /**
     * Cancel a request by its token.
     *
     * Marks the token as cancelled in cache, signaling to the executing request
     * to abort. Safe to call multiple times for the same token. Returns false
     * if token doesn't exist or has expired or if cache operation fails.
     *
     * @param string $token Cancellation token from original request
     *
     * @return bool True if cancellation was successful, false if token not found or cache error
     */
    public function cancel(string $token): bool
    {
        try {
            $key = self::CACHE_PREFIX.$token;
            $status = Cache::get($key);

            if ($status === null) {
                // Token doesn't exist or expired
                return false;
            }

            if ($status === 'cancelled') {
                // Already cancelled
                return true;
            }

            // Mark as cancelled
            Cache::put($key, 'cancelled', $this->tokenTtl);

            return true;
        } catch (Throwable $throwable) {
            // Log the error
            error_log('Failed to cancel request token: '.$throwable->getMessage());

            return false;
        }
    }

    /**
     * Check if a token is cancelled.
     *
     * @param string $token Cancellation token to check
     *
     * @return bool True if token is marked as cancelled, false on cache error
     */
    public function isCancelled(string $token): bool
    {
        try {
            return Cache::get(self::CACHE_PREFIX.$token) === 'cancelled';
        } catch (Throwable $throwable) {
            error_log('Failed to check cancellation status: '.$throwable->getMessage());

            return false;
        }
    }

    /**
     * Check if a token is active.
     *
     * A token is active if it exists in cache and has not been cancelled yet.
     *
     * @param string $token Cancellation token to check
     *
     * @return bool True if token is registered and not cancelled, false on cache error
     */
    public function isActive(string $token): bool
    {
        try {
            return Cache::get(self::CACHE_PREFIX.$token) === 'active';
        } catch (Throwable $throwable) {
            error_log('Failed to check token active status: '.$throwable->getMessage());

            return false;
        }
    }

    /**
     * Clean up a token after request completion.
     *
     * Removes the token from cache. Should be called after request completes
     * (successfully, with error, or via cancellation) to prevent memory leaks.
     *
     * @param string $token Cancellation token to remove
     */
    public function cleanup(string $token): void
    {
        try {
            Cache::forget(self::CACHE_PREFIX.$token);
        } catch (Throwable $throwable) {
            // Log but don't throw - cleanup is best effort
            error_log('Failed to cleanup cancellation token: '.$throwable->getMessage());
        }
    }

    /**
     * Validate cancellation token format.
     *
     * @param string $token Token to validate
     *
     * @throws InvalidArgumentException If token is invalid
     *
     * @return string Validated token
     */
    private function validateToken(string $token): string
    {
        if ($token === '') {
            throw EmptyFieldException::forField('token');
        }

        if (mb_strlen($token) > 100) {
            throw FieldExceedsMaxLengthException::forField('token', 100);
        }

        // Only allow alphanumeric, dash, underscore (UUID-like format recommended)
        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $token)) {
            throw InvalidFieldValueException::forField(
                'token',
                'Only alphanumeric, dash, and underscore characters allowed',
            );
        }

        return $token;
    }
}

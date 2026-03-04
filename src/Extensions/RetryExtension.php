<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions;

use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Events\FunctionExecuted;
use Override;

/**
 * Retry guidance extension handler for Forrst protocol.
 *
 * Provides structured retry information for failed requests according to the Forrst
 * retry extension specification. Automatically adds retry guidance to error responses
 * based on error codes, replacing the deprecated retryable boolean with rich retry
 * semantics including strategies, backoff timings, and attempt limits.
 *
 * The extension attaches retry metadata to responses containing errors, informing
 * clients whether retry is allowed and providing specific guidance on timing and
 * strategy. Error codes are evaluated against predefined retry configurations or
 * ErrorCode enum retryability status.
 *
 * Response data structure:
 * - allowed: bool - Whether retry is permitted for this error
 * - strategy: string - Retry strategy: 'immediate', 'fixed', or 'exponential'
 * - after: object - Minimum wait duration before retry (value + unit)
 * - max_attempts: int - Suggested maximum retry attempts
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/retry
 * @see https://docs.cline.sh/forrst/protocol
 */
final class RetryExtension extends AbstractExtension
{
    /**
     * Immediate retry strategy constant.
     *
     * Indicates retry should be attempted immediately without delay,
     * typically for transient failures that resolve instantly.
     */
    public const string STRATEGY_IMMEDIATE = 'immediate';

    /**
     * Fixed delay retry strategy constant.
     *
     * Indicates retry should wait a fixed duration between attempts,
     * appropriate for rate limits and scheduled maintenance windows.
     */
    public const string STRATEGY_FIXED = 'fixed';

    /**
     * Exponential backoff retry strategy constant.
     *
     * Indicates retry delays should increase exponentially between attempts,
     * ideal for cascading failures and overloaded services to prevent thundering herd.
     */
    public const string STRATEGY_EXPONENTIAL = 'exponential';

    /**
     * Default retry configurations indexed by error code.
     *
     * Defines retry behavior for each retryable error code including strategy type,
     * initial delay in seconds, and maximum recommended attempts. These defaults
     * balance client responsiveness with server protection from retry storms.
     */
    private const array DEFAULT_RETRY_CONFIG = [
        'RATE_LIMITED' => ['strategy' => self::STRATEGY_FIXED, 'after_seconds' => 60, 'max_attempts' => 3],
        'UNAVAILABLE' => ['strategy' => self::STRATEGY_EXPONENTIAL, 'after_seconds' => 1, 'max_attempts' => 5],
        'DEADLINE_EXCEEDED' => ['strategy' => self::STRATEGY_IMMEDIATE, 'after_seconds' => 0, 'max_attempts' => 1],
        'INTERNAL_ERROR' => ['strategy' => self::STRATEGY_EXPONENTIAL, 'after_seconds' => 1, 'max_attempts' => 3],
        'DEPENDENCY_ERROR' => ['strategy' => self::STRATEGY_EXPONENTIAL, 'after_seconds' => 2, 'max_attempts' => 3],
        'IDEMPOTENCY_PROCESSING' => ['strategy' => self::STRATEGY_FIXED, 'after_seconds' => 1, 'max_attempts' => 3],
        'SERVER_MAINTENANCE' => ['strategy' => self::STRATEGY_FIXED, 'after_seconds' => 60, 'max_attempts' => 1],
        'FUNCTION_MAINTENANCE' => ['strategy' => self::STRATEGY_FIXED, 'after_seconds' => 60, 'max_attempts' => 1],
        'FUNCTION_DISABLED' => ['strategy' => self::STRATEGY_FIXED, 'after_seconds' => 30, 'max_attempts' => 2],
    ];

    /**
     * Build custom retry guidance data structure.
     *
     * Utility method for servers to programmatically construct retry extension data
     * with custom parameters. Useful when implementing specialized retry logic beyond
     * the default error code configurations.
     *
     * @param  bool                 $allowed     Whether retry is allowed for this response
     * @param  null|string          $strategy    Retry strategy: 'immediate', 'fixed', or 'exponential'
     * @param  null|int             $afterValue  Wait duration value before retry (omitted for immediate)
     * @param  string               $afterUnit   Wait duration unit (default: 'second')
     * @param  null|int             $maxAttempts Maximum recommended retry attempts
     * @return array<string, mixed> Structured retry extension data ready for response attachment
     */
    public static function buildRetryData(
        bool $allowed,
        ?string $strategy = null,
        ?int $afterValue = null,
        string $afterUnit = 'second',
        ?int $maxAttempts = null,
    ): array {
        $data = ['allowed' => $allowed];

        if ($allowed) {
            if ($strategy !== null) {
                $data['strategy'] = $strategy;
            }

            if ($afterValue !== null) {
                $data['after'] = [
                    'value' => $afterValue,
                    'unit' => $afterUnit,
                ];
            }

            if ($maxAttempts !== null) {
                $data['max_attempts'] = $maxAttempts;
            }
        }

        return $data;
    }

    /**
     * Get the URN identifying this extension.
     *
     * @return string The retry extension URN from the extension registry
     */
    #[Override()]
    public function getUrn(): string
    {
        return ExtensionUrn::Retry->value;
    }

    /**
     * Get event subscriptions for this extension.
     *
     * Subscribes to FunctionExecuted events at priority 150 to attach retry
     * guidance after function execution completes but before response serialization.
     *
     * @return array<string, array{priority: int, method: string}> Event subscription configuration
     */
    #[Override()]
    public function getSubscribedEvents(): array
    {
        return [
            FunctionExecuted::class => [
                'priority' => 150,
                'method' => 'onFunctionExecuted',
            ],
        ];
    }

    /**
     * Add retry guidance metadata to error responses.
     *
     * Analyzes the response for errors and attaches retry extension data based on
     * the primary error code. Non-retryable errors receive {allowed: false}, while
     * retryable errors receive strategy, timing, and attempt limit guidance.
     *
     * @param FunctionExecuted $event The function executed event containing the response
     */
    public function onFunctionExecuted(FunctionExecuted $event): void
    {
        $errors = $event->getResponse()->errors ?? [];

        if ($errors === []) {
            return;
        }

        // Get the primary error code
        $primaryError = $errors[0] ?? null;

        if ($primaryError === null) {
            return;
        }

        $errorCode = $primaryError->code;
        $retryConfig = $this->getRetryConfig($errorCode);

        if ($retryConfig === null) {
            // Error is not retryable
            $event->setResponse($this->addRetryExtension($event->getResponse(), [
                'allowed' => false,
            ]));

            return;
        }

        // Build retry guidance
        $retryData = [
            'allowed' => true,
            'strategy' => $retryConfig['strategy'],
            'max_attempts' => $retryConfig['max_attempts'],
        ];

        // Add after duration if not immediate
        if ($retryConfig['strategy'] !== self::STRATEGY_IMMEDIATE) {
            $retryData['after'] = [
                'value' => $retryConfig['after_seconds'],
                'unit' => 'second',
            ];
        }

        $event->setResponse($this->addRetryExtension($event->getResponse(), $retryData));
    }

    /**
     * Get retry configuration for a specific error code.
     *
     * Looks up retry settings for the given error code, first checking the predefined
     * DEFAULT_RETRY_CONFIG, then falling back to ErrorCode enum retryability check
     * with default exponential backoff settings. Returns null for non-retryable errors.
     *
     * @param  string                                                              $errorCode The error code to look up
     * @return null|array{strategy: string, after_seconds: int, max_attempts: int} Retry configuration or null if not retryable
     */
    private function getRetryConfig(string $errorCode): ?array
    {
        // Check if it's a standard error code
        $enum = ErrorCode::tryFrom($errorCode);

        if ($enum !== null && $enum->isRetryable()) {
            return self::DEFAULT_RETRY_CONFIG[$errorCode] ?? [
                'strategy' => self::STRATEGY_EXPONENTIAL,
                'after_seconds' => 1,
                'max_attempts' => 3,
            ];
        }

        return null;
    }

    /**
     * Add retry extension data to a response object.
     *
     * Creates a new ResponseData instance with retry extension metadata appended
     * to the extensions array. Preserves all original response properties while
     * enriching with retry guidance.
     *
     * @param  ResponseData         $response  Original response object to enrich
     * @param  array<string, mixed> $retryData Retry extension metadata to append
     * @return ResponseData         New response instance with retry extension included
     */
    private function addRetryExtension(ResponseData $response, array $retryData): ResponseData
    {
        $extensions = $response->extensions ?? [];
        $extensions[] = ExtensionData::response(ExtensionUrn::Retry->value, $retryData);

        return new ResponseData(
            protocol: $response->protocol,
            id: $response->id,
            result: $response->result,
            errors: $response->errors,
            extensions: $extensions,
            meta: $response->meta,
        );
    }
}

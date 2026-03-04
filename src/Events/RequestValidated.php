<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Events;

use Cline\Forrst\Data\ErrorData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Enums\ErrorCode;

/**
 * Event dispatched after request parsing and protocol validation.
 *
 * Fired immediately after the raw request body has been parsed and validated
 * against the Forrst protocol schema, but before function resolution or routing.
 * This represents the earliest point in the request lifecycle where extensions
 * can inspect structured request data and make validation or routing decisions.
 *
 * Extensions can use this event for early-stage validation, request rejection,
 * authorization checks, rate limiting, or request transformation before the
 * expensive operation of function resolution and execution begins. Extensions
 * can call stopPropagation() and setResponse() to reject the request early.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/protocol
 */
final class RequestValidated extends ExtensionEvent
{
    /**
     * Reject the request with an error response.
     *
     * Convenience method for early-stage validation failures. Creates
     * an error response and stops propagation, preventing further processing.
     *
     * @param ErrorCode|string          $errorCode Error code (use ErrorCode enum values for standard errors)
     * @param string                    $message   Human-readable error message
     * @param null|array<string, mixed> $metadata  Additional error metadata
     */
    public function rejectRequest(
        ErrorCode|string $errorCode,
        string $message,
        ?array $metadata = null,
    ): void {
        $error = new ErrorData(
            code: $errorCode,
            message: $message,
            details: $metadata,
        );

        $errorResponse = ResponseData::error(
            error: $error,
            id: $this->request->id,
        );

        $this->shortCircuit($errorResponse);
    }

    /**
     * Reject the request due to authorization failure.
     *
     * Specialized rejection for authorization/authentication failures.
     * Sets appropriate error code and stops processing.
     *
     * @param string $reason Reason for authorization failure
     */
    public function rejectUnauthorized(string $reason = 'Authorization required'): void
    {
        $this->rejectRequest(
            errorCode: ErrorCode::Unauthorized,
            message: $reason,
            metadata: ['requested_function' => $this->request->function ?? 'unknown'],
        );
    }

    /**
     * Reject the request due to rate limiting.
     *
     * Specialized rejection for rate limit violations. Includes
     * retry-after metadata when provided.
     *
     * @param null|int $retryAfter Seconds until client can retry
     * @param string   $message    Custom rate limit message
     */
    public function rejectRateLimited(?int $retryAfter = null, string $message = 'Rate limit exceeded'): void
    {
        $metadata = [];

        if ($retryAfter !== null) {
            $metadata['retry_after'] = $retryAfter;
        }

        $this->rejectRequest(
            errorCode: ErrorCode::RateLimited,
            message: $message,
            metadata: $metadata,
        );
    }

    /**
     * Check if the request has been rejected.
     *
     * Returns true if a rejection method was called or if propagation
     * was stopped with an error response.
     *
     * @return bool True if request was rejected
     */
    public function isRejected(): bool
    {
        if (!$this->isPropagationStopped()) {
            return false;
        }

        $response = $this->getResponse();

        return $response instanceof ResponseData && $response->isFailed();
    }
}

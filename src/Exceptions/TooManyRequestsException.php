<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Cline\Forrst\Enums\ErrorCode;
use Override;

/**
 * Exception thrown when rate limiting thresholds are exceeded.
 *
 * Represents rate limit violations where the client has exceeded the allowed number
 * of requests within a specific time window. This exception uses the RateLimited error
 * code and maps to HTTP 429 (Too Many Requests), signaling that clients must implement
 * backoff and retry logic.
 *
 * The exception provides structured rate limit information according to the Forrst
 * rate-limiting extension specification, including the limit threshold, current usage,
 * time window, and retry timing. Clients should use this information to implement
 * appropriate rate limiting and exponential backoff strategies.
 *
 * ```php
 * // Check rate limit
 * if ($rateLimiter->isExceeded($clientId)) {
 *     throw TooManyRequestsException::createWithDetails(
 *         limit: 100,
 *         used: 100,
 *         windowValue: 1,
 *         windowUnit: 'minute',
 *         retryAfterValue: 60,
 *         retryAfterUnit: 'second'
 *     );
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 * @see https://docs.cline.sh/forrst/extensions/rate-limit Rate limit extension
 */
final class TooManyRequestsException extends AbstractRequestException
{
    /**
     * Creates a basic rate limit exception without structured details.
     *
     * Generates a simple rate limit error response with HTTP 429 status code.
     * Use createWithDetails() when you have full rate limit metadata available.
     *
     * @param  null|string $detail Detailed explanation of the rate limit violation,
     *                             such as "Rate limit: 100 requests per minute exceeded".
     *                             When null, a generic message about rate limit exceeded
     *                             is used. This detail appears in the JSON:API error response.
     * @return self        The created exception instance with error code RateLimited,
     *                     default message "Rate limited", and HTTP 429 status.
     */
    public static function create(?string $detail = null): self
    {
        // @phpstan-ignore-next-line argument.type
        return self::new(ErrorCode::RateLimited, 'Rate limited', details: [
            [
                'status' => '429',
                'title' => 'Too Many Requests',
                'detail' => $detail ?? 'The server is refusing to service the request because the rate limit has been exceeded. Please wait and try again later.',
            ],
        ]);
    }

    /**
     * Creates a rate limit exception with spec-compliant structured details.
     *
     * Generates a comprehensive rate limit error response according to the Forrst
     * rate-limiting extension specification. The structured details include the
     * rate limit threshold, current usage count, time window definition, and
     * retry timing to help clients implement proper backoff strategies.
     *
     * @param  int         $limit           Maximum number of requests allowed within the time window,
     *                                      such as 100 for a "100 requests per minute" limit. Clients
     *                                      use this to understand their allocation and implement
     *                                      client-side rate limiting to avoid exceeding the threshold.
     * @param  int         $used            Current number of requests already consumed in the active
     *                                      time window. When used equals limit, the client has fully
     *                                      exhausted their rate limit allocation and must wait for
     *                                      the window to reset before making additional requests.
     * @param  int         $windowValue     Duration value of the rate limit time window, such
     *                                      as 1 for "1 minute" or 60 for "60 seconds". Combined
     *                                      with windowUnit to define the complete time window
     *                                      specification for the rate limit policy.
     * @param  string      $windowUnit      Time unit for the window duration, typically 'second',
     *                                      'minute', or 'hour'. Defines how the windowValue should
     *                                      be interpreted to calculate the complete rate limit
     *                                      time window for request counting.
     * @param  int         $retryAfterValue Duration value until the client should retry, such
     *                                      as 60 for "60 seconds" or 1 for "1 minute". Combined
     *                                      with retryAfterUnit to tell clients exactly when
     *                                      they can safely retry the failed request.
     * @param  string      $retryAfterUnit  Time unit for the retry delay, typically 'second',
     *                                      'minute', or 'hour'. Defines how retryAfterValue
     *                                      should be interpreted to calculate the exact wait
     *                                      time before the client should retry the request.
     * @param  null|string $detail          Optional custom explanation of the rate limit violation.
     *                                      When null, a standard message about rate limit exceeded
     *                                      is used. Can provide context like "API tier limit" or
     *                                      "Per-user request limit exceeded".
     * @return self        The created exception instance with error code RateLimited, structured
     *                     rate limit details conforming to the Forrst rate-limiting extension
     *                     specification, and HTTP 429 status for proper client handling.
     */
    public static function createWithDetails(
        int $limit,
        int $used,
        int $windowValue,
        string $windowUnit,
        int $retryAfterValue,
        string $retryAfterUnit,
        ?string $detail = null,
    ): self {
        return self::new(ErrorCode::RateLimited, 'Rate limited', details: [
            'limit' => $limit,
            'used' => $used,
            'window' => [
                'value' => $windowValue,
                'unit' => $windowUnit,
            ],
            'retry_after' => [
                'value' => $retryAfterValue,
                'unit' => $retryAfterUnit,
            ],
            'status' => '429',
            'title' => 'Too Many Requests',
            'detail' => $detail ?? 'The server is refusing to service the request because the rate limit has been exceeded. Please wait and try again later.',
        ]);
    }

    /**
     * Gets the HTTP status code for this exception.
     *
     * Returns HTTP 429 (Too Many Requests) to indicate the client has exceeded
     * the rate limit and must wait before retrying the request.
     *
     * @return int HTTP 429 Too Many Requests status code
     */
    #[Override()]
    public function getStatusCode(): int
    {
        return 429;
    }
}

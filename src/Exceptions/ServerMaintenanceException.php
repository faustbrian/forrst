<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Cline\Forrst\Enums\ErrorCode;
use DateTimeInterface;
use Override;

use function is_int;
use function is_numeric;

/**
 * Exception thrown when the server is undergoing scheduled maintenance.
 *
 * Represents the ServerMaintenance error code for requests that cannot be processed
 * because the entire server is down for scheduled maintenance. This is a retryable
 * error that maps to HTTP 503 (Service Unavailable) and includes a Retry-After header
 * to inform clients when they should attempt the request again.
 *
 * Unlike general service unavailability, this exception specifically indicates planned
 * downtime with known duration and end time. The exception provides structured information
 * about the maintenance window, reason, and retry timing according to the Forrst maintenance
 * extension specification.
 *
 * ```php
 * // Schedule 30-minute maintenance window
 * throw ServerMaintenanceException::create(
 *     reason: 'Database migration in progress',
 *     until: new DateTime('+30 minutes'),
 *     retryAfter: ['value' => 30, 'unit' => 'minute']
 * );
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 * @see https://docs.cline.sh/forrst/extensions/maintenance Maintenance extension
 */
final class ServerMaintenanceException extends AbstractRequestException
{
    /**
     * Creates a server maintenance exception with optional timing details.
     *
     * Generates a Forrst-compliant maintenance error response with HTTP 503 status code
     * and Retry-After header. The exception includes structured information about the
     * maintenance window, reason, and expected end time according to the Forrst maintenance
     * extension specification.
     *
     * @param  string                    $reason     Human-readable explanation of why the server is under
     *                                               maintenance, such as "Database migration in progress" or
     *                                               "Scheduled system upgrade". Defaults to a generic maintenance
     *                                               message. This appears in error responses to inform clients
     *                                               about the nature of the downtime.
     * @param  null|DateTimeInterface    $until      Expected end time of the maintenance window
     *                                               in any timezone. The time is converted to
     *                                               RFC3339 format for the error response. When
     *                                               null, the maintenance end time is unknown.
     * @param  null|array<string, mixed> $retryAfter Duration until the client should retry,
     *                                               structured as ['value' => int, 'unit' => string]
     *                                               where unit is 'second', 'minute', or 'hour'.
     *                                               This is converted to seconds for the Retry-After
     *                                               header. When null, no retry timing is specified.
     * @return self                      The created exception instance with error code ServerMaintenance,
     *                                   the provided reason as message, and structured maintenance details
     *                                   including timing information for client retry logic.
     */
    public static function create(
        string $reason = 'Service under scheduled maintenance',
        ?DateTimeInterface $until = null,
        ?array $retryAfter = null,
    ): self {
        $details = [
            'reason' => $reason,
        ];

        if ($until instanceof DateTimeInterface) {
            $details['until'] = $until->format(DateTimeInterface::RFC3339);
        }

        if ($retryAfter !== null) {
            $details['retry_after'] = $retryAfter;
        }

        return self::new(
            ErrorCode::ServerMaintenance,
            $reason,
            details: $details,
        );
    }

    /**
     * Calculates the Retry-After header value in seconds.
     *
     * Converts the structured retry_after duration from the error details into
     * seconds for use in the HTTP Retry-After header. Supports conversion from
     * second, minute, and hour units with fallback to the raw value for unknown units.
     *
     * @return null|int Number of seconds until the client should retry the request,
     *                  or null if no retry timing was specified when creating the
     *                  exception. This value is used directly in the Retry-After header.
     */
    public function getRetryAfterSeconds(): ?int
    {
        $retryAfter = $this->error->details['retry_after'] ?? null;

        if ($retryAfter === null) {
            return null;
        }

        // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
        $rawValue = $retryAfter['value'] ?? 0;
        // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
        $unit = $retryAfter['unit'] ?? 'second';

        $value = is_int($rawValue) ? $rawValue : (is_numeric($rawValue) ? (int) $rawValue : 0);

        return match ($unit) {
            'second' => $value,
            'minute' => $value * 60,
            'hour' => $value * 3_600,
            default => $value,
        };
    }

    /**
     * Gets HTTP headers for the exception response.
     *
     * Extends the parent headers with a Retry-After header when retry timing
     * is available. The Retry-After header tells clients when they should retry
     * the request after the maintenance window ends.
     *
     * @return array<string, string> Associative array of HTTP headers including
     *                               Content-Type for JSON:API and Retry-After when
     *                               retry timing was specified in the exception details.
     */
    #[Override()]
    public function getHeaders(): array
    {
        $headers = parent::getHeaders();
        $retryAfter = $this->getRetryAfterSeconds();

        if ($retryAfter !== null) {
            $headers['Retry-After'] = (string) $retryAfter;
        }

        return $headers;
    }
}

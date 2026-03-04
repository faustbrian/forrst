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
use function sprintf;

/**
 * Exception thrown when a specific function is under scheduled maintenance.
 *
 * Represents Forrst error code FUNCTION_MAINTENANCE for requests to functions that
 * are temporarily unavailable due to scheduled maintenance. Other functions may
 * still be available. This is a retryable error that results in HTTP 503 status
 * with a Retry-After header to inform clients when to retry the request.
 *
 * Unlike server-wide maintenance, this exception targets individual functions,
 * allowing partial system availability during maintenance windows.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 * @see https://docs.cline.sh/forrst/extensions/maintenance Maintenance extension
 */
final class FunctionMaintenanceException extends AbstractRequestException
{
    /**
     * Creates a function maintenance exception.
     *
     * @param string                    $function   The affected function name that is under maintenance.
     *                                              Used to identify which specific RPC function is
     *                                              temporarily unavailable.
     * @param string                    $reason     Human-readable explanation of the maintenance
     *                                              operation. Default message indicates scheduled
     *                                              maintenance is in progress.
     * @param null|DateTimeInterface    $until      When maintenance ends (if known). Formatted as
     *                                              RFC3339 timestamp in the error details. Helps
     *                                              clients plan retry strategies and inform users.
     * @param null|array<string, mixed> $retryAfter Retry after duration with 'value' and 'unit' keys.
     *                                              Units can be 'second', 'minute', or 'hour'. Used
     *                                              to generate the Retry-After HTTP header value.
     *
     * @return self A new function maintenance exception with HTTP 503 status, Forrst
     *              error code FUNCTION_MAINTENANCE, and optional Retry-After header.
     */
    public static function create(
        string $function,
        string $reason = 'Function under scheduled maintenance',
        ?DateTimeInterface $until = null,
        ?array $retryAfter = null,
    ): self {
        $details = [
            'function' => $function,
            'reason' => $reason,
        ];

        if ($until instanceof DateTimeInterface) {
            $details['until'] = $until->format(DateTimeInterface::RFC3339);
        }

        if ($retryAfter !== null) {
            $details['retry_after'] = $retryAfter;
        }

        return self::new(
            ErrorCode::FunctionMaintenance,
            sprintf('Function %s under scheduled maintenance', $function),
            details: $details,
        );
    }

    /**
     * Get the affected function name.
     *
     * @return null|string The function name from error details, or null if not set.
     */
    public function getFunction(): ?string
    {
        // @phpstan-ignore-next-line return.type
        return $this->error->details['function'] ?? null;
    }

    /**
     * Get the Retry-After header value in seconds.
     *
     * Converts the retry_after duration from the error details into seconds,
     * supporting 'second', 'minute', and 'hour' units. Used to generate the
     * HTTP Retry-After header for client retry logic.
     *
     * @return null|int Seconds until retry is recommended, or null if retry_after
     *                  duration was not specified in the error details.
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
     * Get HTTP headers for the maintenance response.
     *
     * Extends the parent headers to include the Retry-After header when a retry
     * duration is specified. The Retry-After header informs HTTP clients when
     * they should retry the failed request.
     *
     * @return array<string, string> HTTP headers including Retry-After if duration
     *                               is specified, merged with parent headers.
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

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions;

use Carbon\CarbonImmutable;
use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\ResponseData;
use DateTimeInterface;
use Override;

use function max;

/**
 * Maintenance mode extension handler.
 *
 * Signals scheduled or emergency maintenance windows at the server or function level.
 * Provides clients with clear maintenance status, expected duration, and retry guidance.
 * Unlike FUNCTION_DISABLED errors (permanent or indefinite unavailability), maintenance
 * mode indicates temporary unavailability with a defined or estimated recovery time.
 *
 * Response data:
 * - scope: 'server' (entire system) or 'function' (specific function only)
 * - function: Name of affected function (if scope is 'function')
 * - reason: Human-readable explanation for the maintenance window
 * - started_at: ISO 8601 timestamp when maintenance began
 * - until: ISO 8601 timestamp when maintenance is expected to end (if known)
 * - retry_after: Duration before client should retry (value and unit object)
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/maintenance
 */
final class MaintenanceExtension extends AbstractExtension
{
    /**
     * Maintenance scopes.
     */
    public const string SCOPE_SERVER = 'server';

    public const string SCOPE_FUNCTION = 'function';

    /**
     * Time units.
     */
    public const string UNIT_SECOND = 'second';

    public const string UNIT_MINUTE = 'minute';

    public const string UNIT_HOUR = 'hour';

    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function getUrn(): string
    {
        return ExtensionUrn::Maintenance->value;
    }

    /**
     * {@inheritDoc}
     *
     * Maintenance mode errors are fatal - they should block the request.
     */
    #[Override()]
    public function isErrorFatal(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * Maintenance extension is global - it runs on all requests to check maintenance status.
     */
    #[Override()]
    public function isGlobal(): bool
    {
        return true;
    }

    /**
     * Build a time duration object.
     *
     * Creates a standardized duration object for retry-after or estimated
     * recovery time. Used consistently across maintenance responses.
     *
     * @param  int                  $value The numeric duration value
     * @param  string               $unit  The time unit (second, minute, hour)
     * @return array<string, mixed> Duration object with value and unit fields
     */
    public function buildDuration(int $value, string $unit): array
    {
        return [
            'value' => $value,
            'unit' => $unit,
        ];
    }

    /**
     * Build server maintenance data.
     *
     * Creates maintenance metadata for server-wide maintenance affecting all
     * functions. Used when the entire system is unavailable for upgrades,
     * infrastructure changes, or emergency repairs.
     *
     * @param  string                 $reason     Human-readable explanation of maintenance purpose
     * @param  DateTimeInterface      $startedAt  ISO 8601 timestamp when maintenance began
     * @param  null|DateTimeInterface $until      ISO 8601 timestamp when maintenance ends (null if unknown)
     * @param  int                    $retryValue Suggested retry delay numeric value (default: 30)
     * @param  string                 $retryUnit  Retry delay time unit (default: minute)
     * @return array<string, mixed>   Server maintenance metadata with scope, reason, and timing
     */
    public function buildServerMaintenance(
        string $reason,
        DateTimeInterface $startedAt,
        ?DateTimeInterface $until = null,
        int $retryValue = 30,
        string $retryUnit = self::UNIT_MINUTE,
    ): array {
        $data = [
            'scope' => self::SCOPE_SERVER,
            'reason' => $reason,
            'started_at' => $startedAt->format(DateTimeInterface::RFC3339),
            'retry_after' => $this->buildDuration($retryValue, $retryUnit),
        ];

        if ($until instanceof DateTimeInterface) {
            $data['until'] = $until->format(DateTimeInterface::RFC3339);
        }

        return $data;
    }

    /**
     * Build function maintenance data.
     *
     * @param  string                 $function   The affected function name
     * @param  string                 $reason     Human-readable explanation
     * @param  DateTimeInterface      $startedAt  When maintenance began
     * @param  null|DateTimeInterface $until      When maintenance ends (if known)
     * @param  int                    $retryValue Retry delay value
     * @param  string                 $retryUnit  Retry delay unit
     * @return array<string, mixed>   Function maintenance data
     */
    public function buildFunctionMaintenance(
        string $function,
        string $reason,
        DateTimeInterface $startedAt,
        ?DateTimeInterface $until = null,
        int $retryValue = 15,
        string $retryUnit = self::UNIT_MINUTE,
    ): array {
        $data = [
            'scope' => self::SCOPE_FUNCTION,
            'function' => $function,
            'reason' => $reason,
            'started_at' => $startedAt->format(DateTimeInterface::RFC3339),
            'retry_after' => $this->buildDuration($retryValue, $retryUnit),
        ];

        if ($until instanceof DateTimeInterface) {
            $data['until'] = $until->format(DateTimeInterface::RFC3339);
        }

        return $data;
    }

    /**
     * Build error details for server maintenance.
     *
     * @param  string                 $reason     Human-readable explanation
     * @param  DateTimeInterface      $startedAt  When maintenance began
     * @param  null|DateTimeInterface $until      When maintenance ends (if known)
     * @param  int                    $retryValue Retry delay value
     * @param  string                 $retryUnit  Retry delay unit
     * @return array<string, mixed>   Error details
     */
    public function buildServerMaintenanceErrorDetails(
        string $reason,
        DateTimeInterface $startedAt,
        ?DateTimeInterface $until = null,
        int $retryValue = 30,
        string $retryUnit = self::UNIT_MINUTE,
    ): array {
        $details = [
            'reason' => $reason,
            'started_at' => $startedAt->format(DateTimeInterface::RFC3339),
            'retry_after' => $this->buildDuration($retryValue, $retryUnit),
        ];

        if ($until instanceof DateTimeInterface) {
            $details['until'] = $until->format(DateTimeInterface::RFC3339);
        }

        return $details;
    }

    /**
     * Build error details for function maintenance.
     *
     * @param  string                 $function   The affected function name
     * @param  string                 $reason     Human-readable explanation
     * @param  DateTimeInterface      $startedAt  When maintenance began
     * @param  null|DateTimeInterface $until      When maintenance ends (if known)
     * @param  int                    $retryValue Retry delay value
     * @param  string                 $retryUnit  Retry delay unit
     * @return array<string, mixed>   Error details
     */
    public function buildFunctionMaintenanceErrorDetails(
        string $function,
        string $reason,
        DateTimeInterface $startedAt,
        ?DateTimeInterface $until = null,
        int $retryValue = 15,
        string $retryUnit = self::UNIT_MINUTE,
    ): array {
        $details = [
            'function' => $function,
            'reason' => $reason,
            'started_at' => $startedAt->format(DateTimeInterface::RFC3339),
            'retry_after' => $this->buildDuration($retryValue, $retryUnit),
        ];

        if ($until instanceof DateTimeInterface) {
            $details['until'] = $until->format(DateTimeInterface::RFC3339);
        }

        return $details;
    }

    /**
     * Enrich a response with maintenance information.
     *
     * @param  ResponseData         $response    Original response
     * @param  array<string, mixed> $maintenance Maintenance data
     * @return ResponseData         Enriched response
     */
    public function enrichResponse(ResponseData $response, array $maintenance): ResponseData
    {
        $extensions = $response->extensions ?? [];
        $extensions[] = ExtensionData::response(ExtensionUrn::Maintenance->value, $maintenance);

        return new ResponseData(
            protocol: $response->protocol,
            id: $response->id,
            result: $response->result,
            errors: $response->errors,
            extensions: $extensions,
            meta: $response->meta,
        );
    }

    /**
     * Calculate retry-after header value in seconds.
     *
     * @param  int    $value Retry delay value
     * @param  string $unit  Retry delay unit
     * @return int    Seconds
     */
    public function calculateRetryAfterSeconds(int $value, string $unit): int
    {
        return match ($unit) {
            self::UNIT_SECOND => $value,
            self::UNIT_MINUTE => $value * 60,
            self::UNIT_HOUR => $value * 3_600,
            default => $value,
        };
    }

    /**
     * Check if maintenance is active based on until timestamp.
     *
     * @param  null|DateTimeInterface $until When maintenance ends
     * @return bool                   True if still under maintenance
     */
    public function isMaintenanceActive(?DateTimeInterface $until): bool
    {
        if (!$until instanceof DateTimeInterface) {
            return true; // Unknown end time means still active
        }

        return CarbonImmutable::now() < $until;
    }

    /**
     * Calculate remaining maintenance time in seconds.
     *
     * @param  DateTimeInterface $until When maintenance ends
     * @return int               Remaining seconds (minimum 0)
     */
    public function calculateRemainingSeconds(DateTimeInterface $until): int
    {
        $now = CarbonImmutable::now();
        $remaining = $until->getTimestamp() - $now->getTimestamp();

        return max(0, $remaining);
    }
}

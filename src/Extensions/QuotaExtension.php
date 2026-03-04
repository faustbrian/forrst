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
use Override;

use function max;

/**
 * Quota extension handler.
 *
 * Provides visibility into resource consumption quotas and limits. Returns
 * current usage, remaining capacity, and reset timing for various quota types
 * (requests, compute, storage, bandwidth). Helps clients implement proactive
 * throttling and avoid hitting limits that would result in request failures.
 *
 * Request options:
 * - include: Array of specific quota type names to include (filters response)
 *
 * Response data:
 * - quotas: Array of quota entries with limit, used, remaining, period, and reset timing
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/quota
 */
final class QuotaExtension extends AbstractExtension
{
    /**
     * Quota types.
     */
    public const string TYPE_REQUESTS = 'requests';

    public const string TYPE_COMPUTE = 'compute';

    public const string TYPE_STORAGE = 'storage';

    public const string TYPE_BANDWIDTH = 'bandwidth';

    public const string TYPE_CUSTOM = 'custom';

    /**
     * Quota periods.
     */
    public const string PERIOD_MINUTE = 'minute';

    public const string PERIOD_HOUR = 'hour';

    public const string PERIOD_DAY = 'day';

    public const string PERIOD_MONTH = 'month';

    public const string PERIOD_BILLING_CYCLE = 'billing_cycle';

    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function getUrn(): string
    {
        return ExtensionUrn::Quota->value;
    }

    /**
     * {@inheritDoc}
     *
     * Quota information is advisory, failures should not break requests.
     */
    #[Override()]
    public function isErrorFatal(): bool
    {
        return false;
    }

    /**
     * Get the requested quota types.
     *
     * @param  null|array<string, mixed> $options Extension options
     * @return null|array<int, string>   Quota types to include, or null for all
     */
    public function getIncludedTypes(?array $options): ?array
    {
        // @phpstan-ignore return.type
        return $options['include'] ?? null;
    }

    /**
     * Build a quota entry.
     *
     * Creates a standardized quota object with usage tracking and reset timing.
     * Automatically calculates remaining capacity from limit and used values.
     *
     * @param  string               $type     Quota type constant (requests, compute, storage, etc.)
     * @param  string               $name     Human-readable display name for UI presentation
     * @param  int                  $limit    Maximum allowed units in this period
     * @param  int                  $used     Units already consumed in current period
     * @param  string               $period   Reset period (minute, hour, day, month, billing_cycle)
     * @param  string               $unit     Unit of measurement (requests, tokens, bytes, etc.)
     * @param  null|CarbonImmutable $resetsAt ISO 8601 timestamp when quota resets (optional)
     * @return array<string, mixed> Quota entry with limit, used, remaining, and timing metadata
     */
    public function buildQuota(
        string $type,
        string $name,
        int $limit,
        int $used,
        string $period,
        string $unit,
        ?CarbonImmutable $resetsAt = null,
    ): array {
        $quota = [
            'type' => $type,
            'name' => $name,
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'period' => $period,
            'unit' => $unit,
        ];

        if ($resetsAt instanceof CarbonImmutable) {
            $quota['resets_at'] = $resetsAt->toIso8601String();
        }

        return $quota;
    }

    /**
     * Build a requests quota entry.
     *
     * @param  int                  $limit    Maximum requests
     * @param  int                  $used     Requests used
     * @param  string               $period   Reset period
     * @param  null|CarbonImmutable $resetsAt When quota resets
     * @return array<string, mixed> Quota entry
     */
    public function buildRequestsQuota(
        int $limit,
        int $used,
        string $period = self::PERIOD_MONTH,
        ?CarbonImmutable $resetsAt = null,
    ): array {
        return $this->buildQuota(
            type: self::TYPE_REQUESTS,
            name: 'API Requests',
            limit: $limit,
            used: $used,
            period: $period,
            unit: 'requests',
            resetsAt: $resetsAt,
        );
    }

    /**
     * Build a compute quota entry.
     *
     * @param  int                  $limit    Maximum compute units
     * @param  int                  $used     Compute units used
     * @param  string               $unit     Unit name (e.g., 'tokens', 'credits')
     * @param  string               $period   Reset period
     * @param  null|CarbonImmutable $resetsAt When quota resets
     * @return array<string, mixed> Quota entry
     */
    public function buildComputeQuota(
        int $limit,
        int $used,
        string $unit = 'tokens',
        string $period = self::PERIOD_MONTH,
        ?CarbonImmutable $resetsAt = null,
    ): array {
        return $this->buildQuota(
            type: self::TYPE_COMPUTE,
            name: 'Compute Units',
            limit: $limit,
            used: $used,
            period: $period,
            unit: $unit,
            resetsAt: $resetsAt,
        );
    }

    /**
     * Build a storage quota entry.
     *
     * @param  int                  $limitBytes Maximum storage in bytes
     * @param  int                  $usedBytes  Storage used in bytes
     * @param  string               $period     Reset period
     * @param  null|CarbonImmutable $resetsAt   When quota resets
     * @return array<string, mixed> Quota entry
     */
    public function buildStorageQuota(
        int $limitBytes,
        int $usedBytes,
        string $period = self::PERIOD_BILLING_CYCLE,
        ?CarbonImmutable $resetsAt = null,
    ): array {
        return $this->buildQuota(
            type: self::TYPE_STORAGE,
            name: 'File Storage',
            limit: $limitBytes,
            used: $usedBytes,
            period: $period,
            unit: 'bytes',
            resetsAt: $resetsAt,
        );
    }

    /**
     * Build a bandwidth quota entry.
     *
     * @param  int                  $limitBytes Maximum transfer in bytes
     * @param  int                  $usedBytes  Transfer used in bytes
     * @param  string               $period     Reset period
     * @param  null|CarbonImmutable $resetsAt   When quota resets
     * @return array<string, mixed> Quota entry
     */
    public function buildBandwidthQuota(
        int $limitBytes,
        int $usedBytes,
        string $period = self::PERIOD_MONTH,
        ?CarbonImmutable $resetsAt = null,
    ): array {
        return $this->buildQuota(
            type: self::TYPE_BANDWIDTH,
            name: 'Monthly Transfer',
            limit: $limitBytes,
            used: $usedBytes,
            period: $period,
            unit: 'bytes',
            resetsAt: $resetsAt,
        );
    }

    /**
     * Build a custom quota entry.
     *
     * @param  string               $name     Human-readable name
     * @param  int                  $limit    Maximum allowed
     * @param  int                  $used     Currently used
     * @param  string               $unit     Unit of measurement
     * @param  string               $period   Reset period
     * @param  null|CarbonImmutable $resetsAt When quota resets
     * @return array<string, mixed> Quota entry
     */
    public function buildCustomQuota(
        string $name,
        int $limit,
        int $used,
        string $unit,
        string $period = self::PERIOD_BILLING_CYCLE,
        ?CarbonImmutable $resetsAt = null,
    ): array {
        return $this->buildQuota(
            type: self::TYPE_CUSTOM,
            name: $name,
            limit: $limit,
            used: $used,
            period: $period,
            unit: $unit,
            resetsAt: $resetsAt,
        );
    }

    /**
     * Enrich a response with quota information.
     *
     * @param  ResponseData                     $response Original response
     * @param  array<int, array<string, mixed>> $quotas   Quota entries
     * @return ResponseData                     Enriched response
     */
    public function enrichResponse(ResponseData $response, array $quotas): ResponseData
    {
        $extensions = $response->extensions ?? [];
        $extensions[] = ExtensionData::response(ExtensionUrn::Quota->value, [
            'quotas' => $quotas,
        ]);

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
     * Check if quota is near limit.
     *
     * @param  int   $limit     Maximum allowed
     * @param  int   $used      Currently used
     * @param  float $threshold Percentage threshold (default 80%)
     * @return bool  True if usage is at or above threshold
     */
    public function isNearLimit(int $limit, int $used, float $threshold = 0.8): bool
    {
        if ($limit <= 0) {
            return false;
        }

        return ($used / $limit) >= $threshold;
    }

    /**
     * Check if quota is exceeded.
     *
     * @param  int  $limit Maximum allowed
     * @param  int  $used  Currently used
     * @return bool True if limit is exceeded
     */
    public function isExceeded(int $limit, int $used): bool
    {
        return $used >= $limit;
    }
}

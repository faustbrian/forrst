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
use Override;

use function assert;
use function is_string;
use function max;

/**
 * Rate limit extension handler.
 *
 * Provides real-time rate limit information in responses to help clients understand
 * their current throttling status and avoid hitting limits. Returns limit, usage,
 * remaining requests, window duration, and reset timing. Supports multiple scopes
 * (global, service, function, user) for granular rate limit tracking.
 *
 * Request options:
 * - scope: Request specific scope information (global, service, function, user)
 *
 * Response data:
 * - limit: Maximum requests allowed in the current time window
 * - used: Number of requests consumed in the current window
 * - remaining: Requests remaining before hitting the limit
 * - window: Time window duration as value and unit object
 * - resets_in: Duration until the window resets as value and unit object
 * - scope: The scope this limit applies to (global, service, function, user)
 * - warning: Optional warning message when approaching the limit threshold
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/rate-limit
 */
final class RateLimitExtension extends AbstractExtension
{
    /**
     * Rate limit scopes.
     */
    public const string SCOPE_GLOBAL = 'global';

    public const string SCOPE_SERVICE = 'service';

    public const string SCOPE_FUNCTION = 'function';

    public const string SCOPE_USER = 'user';

    /**
     * Time units.
     */
    public const string UNIT_SECOND = 'second';

    public const string UNIT_MINUTE = 'minute';

    public const string UNIT_HOUR = 'hour';

    /**
     * Default warning threshold (percentage).
     */
    public const float DEFAULT_WARNING_THRESHOLD = 0.1;

    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function getUrn(): string
    {
        return ExtensionUrn::RateLimit->value;
    }

    /**
     * {@inheritDoc}
     *
     * Rate limit information is advisory, failures should not break requests.
     */
    #[Override()]
    public function isErrorFatal(): bool
    {
        return false;
    }

    /**
     * Get the requested scope from options.
     *
     * @param  null|array<string, mixed> $options Extension options
     * @return null|string               Requested scope, or null for default
     */
    public function getRequestedScope(?array $options): ?string
    {
        $value = $options['scope'] ?? null;
        assert($value === null || is_string($value));

        return $value;
    }

    /**
     * Build a time duration object.
     *
     * @param  int                  $value The numeric value
     * @param  string               $unit  The time unit (second, minute, hour)
     * @return array<string, mixed> Duration object with value and unit
     */
    public function buildDuration(int $value, string $unit): array
    {
        return [
            'value' => $value,
            'unit' => $unit,
        ];
    }

    /**
     * Build a rate limit entry.
     *
     * Creates a complete rate limit status object with usage tracking and reset
     * timing. Automatically calculates remaining requests and adds warnings when
     * approaching the limit threshold.
     *
     * @param  int                  $limit       Maximum requests allowed in the window
     * @param  int                  $used        Requests already consumed in current window
     * @param  int                  $windowValue Time window numeric value
     * @param  string               $windowUnit  Time window unit (second, minute, hour)
     * @param  int                  $resetsIn    Seconds until the window resets
     * @param  string               $scope       Scope identifier (global, service, function, user)
     * @param  null|string          $warning     Optional custom warning message (auto-generated if null)
     * @return array<string, mixed> Rate limit entry with usage, timing, and warning metadata
     */
    public function buildRateLimit(
        int $limit,
        int $used,
        int $windowValue,
        string $windowUnit,
        int $resetsIn,
        string $scope,
        ?string $warning = null,
    ): array {
        $remaining = max(0, $limit - $used);

        $rateLimit = [
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
            'window' => $this->buildDuration($windowValue, $windowUnit),
            'resets_in' => $this->buildDuration($resetsIn, self::UNIT_SECOND),
            'scope' => $scope,
        ];

        if ($warning !== null) {
            $rateLimit['warning'] = $warning;
        } elseif ($this->isNearLimit($limit, $used)) {
            $rateLimit['warning'] = 'Rate limit nearly exhausted';
        }

        return $rateLimit;
    }

    /**
     * Build rate limit data with multiple scopes.
     *
     * @param  array<string, array<string, mixed>> $scopes Scope name => rate limit data
     * @return array<string, mixed>                Multi-scope rate limit data
     */
    public function buildMultiScopeData(array $scopes): array
    {
        return [
            'scopes' => $scopes,
        ];
    }

    /**
     * Build a scope entry for multi-scope responses.
     *
     * @param  int                  $limit       Maximum requests allowed
     * @param  int                  $used        Requests used in current window
     * @param  int                  $windowValue Time window value
     * @param  string               $windowUnit  Time window unit
     * @param  int                  $resetsIn    Seconds until window resets
     * @return array<string, mixed> Scope entry (without scope field)
     */
    public function buildScopeEntry(
        int $limit,
        int $used,
        int $windowValue,
        string $windowUnit,
        int $resetsIn,
    ): array {
        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'window' => $this->buildDuration($windowValue, $windowUnit),
            'resets_in' => $this->buildDuration($resetsIn, self::UNIT_SECOND),
        ];
    }

    /**
     * Enrich a response with rate limit information.
     *
     * @param  ResponseData         $response  Original response
     * @param  array<string, mixed> $rateLimit Rate limit data
     * @return ResponseData         Enriched response
     */
    public function enrichResponse(ResponseData $response, array $rateLimit): ResponseData
    {
        $extensions = $response->extensions ?? [];
        $extensions[] = ExtensionData::response(ExtensionUrn::RateLimit->value, $rateLimit);

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
     * Check if usage is near the limit.
     *
     * @param  int   $limit     Maximum allowed
     * @param  int   $used      Currently used
     * @param  float $threshold Percentage threshold (default 10% remaining)
     * @return bool  True if remaining is at or below threshold
     */
    public function isNearLimit(int $limit, int $used, float $threshold = self::DEFAULT_WARNING_THRESHOLD): bool
    {
        if ($limit <= 0) {
            return false;
        }

        $remaining = max(0, $limit - $used);

        return ($remaining / $limit) <= $threshold;
    }

    /**
     * Check if rate limit is exceeded.
     *
     * @param  int  $limit Maximum allowed
     * @param  int  $used  Currently used
     * @return bool True if limit is exceeded
     */
    public function isExceeded(int $limit, int $used): bool
    {
        return $used >= $limit;
    }

    /**
     * Calculate remaining requests.
     *
     * @param  int $limit Maximum allowed
     * @param  int $used  Currently used
     * @return int Remaining requests (minimum 0)
     */
    public function calculateRemaining(int $limit, int $used): int
    {
        return max(0, $limit - $used);
    }
}

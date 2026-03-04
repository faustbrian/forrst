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

use function array_key_exists;
use function array_keys;
use function assert;
use function is_string;

/**
 * Priority extension handler.
 *
 * Allows clients to signal request urgency, enabling servers to implement priority-based
 * queue management and resource allocation. Servers may honor, adjust, or ignore priority
 * hints based on authentication, quotas, or service tiers. Helps optimize system throughput
 * by ensuring critical operations are processed before bulk background tasks.
 *
 * Request options:
 * - level: Priority level (critical, high, normal, low, bulk)
 * - reason: Optional explanation for why this priority was requested
 *
 * Response data:
 * - honored: Boolean indicating whether the requested priority was applied
 * - effective_level: Actual priority level used by the server
 * - queue_position: Current position in processing queue (if queued)
 * - wait_time: Time spent waiting in queue (value and unit object)
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/priority
 */
final class PriorityExtension extends AbstractExtension
{
    /**
     * Priority levels.
     */
    public const string LEVEL_CRITICAL = 'critical';

    public const string LEVEL_HIGH = 'high';

    public const string LEVEL_NORMAL = 'normal';

    public const string LEVEL_LOW = 'low';

    public const string LEVEL_BULK = 'bulk';

    /**
     * Priority level values for sorting.
     */
    private const array LEVEL_VALUES = [
        self::LEVEL_CRITICAL => 5,
        self::LEVEL_HIGH => 4,
        self::LEVEL_NORMAL => 3,
        self::LEVEL_LOW => 2,
        self::LEVEL_BULK => 1,
    ];

    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function getUrn(): string
    {
        return ExtensionUrn::Priority->value;
    }

    /**
     * {@inheritDoc}
     *
     * Priority is a hint, failures should not break requests.
     */
    #[Override()]
    public function isErrorFatal(): bool
    {
        return false;
    }

    /**
     * Get the requested priority level.
     *
     * Extracts and validates the priority level from request options. Returns
     * the normal priority level as fallback for invalid or missing values.
     *
     * @param  null|array<string, mixed> $options Extension options from request
     * @return string                    Valid priority level constant
     */
    public function getLevel(?array $options): string
    {
        $level = $options['level'] ?? self::LEVEL_NORMAL;

        if (!is_string($level)) {
            return self::LEVEL_NORMAL;
        }

        if (!array_key_exists($level, self::LEVEL_VALUES)) {
            return self::LEVEL_NORMAL;
        }

        return $level;
    }

    /**
     * Get the reason for the priority.
     *
     * @param  null|array<string, mixed> $options Extension options
     * @return null|string               Priority reason
     */
    public function getReason(?array $options): ?string
    {
        $value = $options['reason'] ?? null;
        assert($value === null || is_string($value));

        return $value;
    }

    /**
     * Get the numeric value for a priority level.
     *
     * Converts a priority level string to its numeric ranking for comparison
     * and sorting operations. Higher numbers indicate higher priority.
     *
     * @param  string $level Priority level constant
     * @return int    Numeric priority value (1-5, higher = more urgent)
     */
    public function getLevelValue(string $level): int
    {
        return self::LEVEL_VALUES[$level] ?? self::LEVEL_VALUES[self::LEVEL_NORMAL];
    }

    /**
     * Compare two priority levels.
     *
     * @param  string $a First level
     * @param  string $b Second level
     * @return int    Negative if a < b, positive if a > b, zero if equal
     */
    public function compareLevels(string $a, string $b): int
    {
        return $this->getLevelValue($a) <=> $this->getLevelValue($b);
    }

    /**
     * Check if level a has higher priority than level b.
     *
     * @param  string $a First level
     * @param  string $b Second level
     * @return bool   True if a has higher priority than b
     */
    public function isHigherPriority(string $a, string $b): bool
    {
        return $this->compareLevels($a, $b) > 0;
    }

    /**
     * Get all valid priority levels.
     *
     * @return array<int, string> Valid levels
     */
    public function getValidLevels(): array
    {
        return array_keys(self::LEVEL_VALUES);
    }

    /**
     * Enrich a response with priority metadata.
     *
     * @param  ResponseData                         $response       Original response
     * @param  bool                                 $honored        Whether priority was applied
     * @param  string                               $effectiveLevel Actual priority used
     * @param  null|int                             $queuePosition  Position in queue
     * @param  null|array{value: int, unit: string} $waitTime       Time spent waiting
     * @return ResponseData                         Enriched response
     */
    public function enrichResponse(
        ResponseData $response,
        bool $honored,
        string $effectiveLevel,
        ?int $queuePosition = null,
        ?array $waitTime = null,
    ): ResponseData {
        $data = [
            'honored' => $honored,
            'effective_level' => $effectiveLevel,
        ];

        if ($queuePosition !== null) {
            $data['queue_position'] = $queuePosition;
        }

        if ($waitTime !== null) {
            $data['wait_time'] = $waitTime;
        }

        $extensions = $response->extensions ?? [];
        $extensions[] = ExtensionData::response(ExtensionUrn::Priority->value, $data);

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
     * Build priority response data.
     *
     * @param  bool                 $honored        Whether priority was honored
     * @param  string               $effectiveLevel Actual priority used
     * @param  null|int             $queuePosition  Queue position
     * @param  null|int             $waitTimeMs     Wait time in milliseconds
     * @return array<string, mixed> Priority response data
     */
    public function buildResponseData(
        bool $honored,
        string $effectiveLevel,
        ?int $queuePosition = null,
        ?int $waitTimeMs = null,
    ): array {
        $data = [
            'honored' => $honored,
            'effective_level' => $effectiveLevel,
        ];

        if ($queuePosition !== null) {
            $data['queue_position'] = $queuePosition;
        }

        if ($waitTimeMs !== null) {
            $data['wait_time'] = [
                'value' => $waitTimeMs,
                'unit' => 'millisecond',
            ];
        }

        return $data;
    }
}

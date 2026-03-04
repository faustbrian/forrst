<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Enums;

use function array_map;
use function mb_strtolower;

/**
 * Priority levels for replay operations in the Forrst replay extension.
 *
 * Defines execution priority for queued replay operations. Higher priority
 * replays are processed before lower priority ones when multiple replays
 * are queued. Priority affects scheduling order but does not guarantee
 * immediate execution.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/replay
 */
enum ReplayPriority: string
{
    /**
     * High priority replay operations processed before normal and low priority.
     *
     * Used for time-sensitive or critical replays that should be processed
     * as soon as possible when the replay queue has capacity.
     */
    case High = 'high';

    /**
     * Normal priority replay operations processed in standard queue order.
     *
     * Default priority level for most replay operations. Provides balanced
     * scheduling without preferential treatment or deferral.
     */
    case Normal = 'normal';

    /**
     * Low priority replay operations processed after normal and high priority.
     *
     * Used for non-urgent replays that can be deferred when higher priority
     * work is available. Useful for batch operations or background tasks.
     */
    case Low = 'low';

    /**
     * Get the default priority level.
     *
     * Returns the standard priority used when no explicit priority is
     * specified. Centralizes the default value for consistency across
     * the application.
     *
     * @return self The default priority level (Normal)
     */
    public static function default(): self
    {
        return self::Normal;
    }

    /**
     * Parse a priority string to a ReplayPriority enum case.
     *
     * Attempts to create a ReplayPriority from a string value with
     * case-insensitive matching. Returns null if the value doesn't
     * match any valid priority level.
     *
     * @param  string    $value Priority value to parse (e.g., 'high', 'NORMAL', 'Low')
     * @return null|self Matched priority or null if invalid
     */
    public static function tryFromString(string $value): ?self
    {
        $normalized = mb_strtolower($value);

        return match ($normalized) {
            'high' => self::High,
            'normal' => self::Normal,
            'low' => self::Low,
            default => null,
        };
    }

    /**
     * Parse a priority string or return the default priority.
     *
     * Convenience method combining tryFromString() with a default fallback.
     * Useful for parsing optional priority values from requests.
     *
     * @param  null|string $value Priority value to parse (null returns default)
     * @return self        Parsed priority or default priority
     */
    public static function fromOrDefault(?string $value): self
    {
        if ($value === null) {
            return self::default();
        }

        return self::tryFromString($value) ?? self::default();
    }

    /**
     * Get all valid priority values as strings.
     *
     * Returns an array of valid string values for validation, documentation,
     * or UI dropdown generation. Values are lowercase to match API convention.
     *
     * @return array<string> Valid priority values ['high', 'normal', 'low']
     */
    public static function values(): array
    {
        return array_map(
            fn (self $case) => $case->value,
            self::cases(),
        );
    }

    /**
     * Get the numeric weight for this priority level.
     *
     * Higher values indicate higher priority. Used for queue sorting
     * and priority comparisons. Values are spaced to allow future
     * priority levels to be inserted between existing ones.
     *
     * @return int Priority weight (10=low, 50=normal, 90=high)
     */
    public function getWeight(): int
    {
        return match ($this) {
            self::High => 90,
            self::Normal => 50,
            self::Low => 10,
        };
    }

    /**
     * Compare this priority with another priority.
     *
     * Returns a negative number if this priority is lower, zero if equal,
     * or a positive number if this priority is higher. Compatible with
     * usort() and other comparison-based sorting functions.
     *
     * @param  self $other Priority to compare against
     * @return int  Comparison result: negative (lower), 0 (equal), positive (higher)
     */
    public function compareTo(self $other): int
    {
        return $this->getWeight() <=> $other->getWeight();
    }

    /**
     * Check if this priority is higher than another priority.
     *
     * @param  self $other Priority to compare against
     * @return bool True if this priority should be processed before the other
     */
    public function isHigherThan(self $other): bool
    {
        return $this->getWeight() > $other->getWeight();
    }

    /**
     * Check if this priority is lower than another priority.
     *
     * @param  self $other Priority to compare against
     * @return bool True if this priority should be processed after the other
     */
    public function isLowerThan(self $other): bool
    {
        return $this->getWeight() < $other->getWeight();
    }

    /**
     * Check if this priority is the same as another priority.
     *
     * @param  self $other Priority to compare against
     * @return bool True if priorities are equal
     */
    public function isEqualTo(self $other): bool
    {
        return $this === $other;
    }
}

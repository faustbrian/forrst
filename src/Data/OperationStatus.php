<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Data;

/**
 * Operation status values.
 *
 * Represents the lifecycle states of an async operation following a state
 * machine pattern. Operations progress from Pending through Processing to
 * a terminal state (Completed, Failed, or Cancelled).
 *
 * State transitions:
 * - Pending -> Processing (when execution begins)
 * - Processing -> Completed (on success)
 * - Processing -> Failed (on error)
 * - Pending|Processing -> Cancelled (when cancelled)
 *
 * Terminal states cannot transition to other states.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/extensions/async
 */
enum OperationStatus: string
{
    /**
     * Operation is waiting to start.
     *
     * Initial state when operation is queued but not yet executing.
     * Can transition to Processing or Cancelled.
     */
    case Pending = 'pending';

    /**
     * Operation is currently executing.
     *
     * Active execution state. Can transition to Completed, Failed, or Cancelled.
     */
    case Processing = 'processing';

    /**
     * Operation completed successfully.
     *
     * Terminal state indicating successful execution with a result.
     * Cannot transition to other states.
     */
    case Completed = 'completed';

    /**
     * Operation failed with errors.
     *
     * Terminal state indicating execution failed with one or more errors.
     * Cannot transition to other states.
     */
    case Failed = 'failed';

    /**
     * Operation was cancelled.
     *
     * Terminal state indicating execution was cancelled before completion.
     * Cannot transition to other states.
     */
    case Cancelled = 'cancelled';

    /**
     * Check if status is terminal (cannot change).
     *
     * Terminal states represent final outcomes where no further state
     * transitions are possible. These are: Completed, Failed, Cancelled.
     *
     * @return bool True for terminal states
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed, self::Cancelled => true,
            default => false,
        };
    }

    /**
     * Check if status is in progress.
     *
     * In-progress states indicate the operation is not yet in a terminal
     * state and may still change. These are: Pending, Processing.
     *
     * @return bool True for in-progress states
     */
    public function isInProgress(): bool
    {
        return match ($this) {
            self::Pending, self::Processing => true,
            default => false,
        };
    }
}

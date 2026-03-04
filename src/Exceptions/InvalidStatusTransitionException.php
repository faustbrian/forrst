<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use RuntimeException;

use function sprintf;

/**
 * Exception thrown when attempting an invalid replay status transition.
 *
 * This exception is raised when a status transition violates the replay
 * lifecycle state machine rules, such as attempting to transition from
 * a terminal state or attempting a transition that is not allowed by
 * the defined state transition matrix.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidStatusTransitionException extends RuntimeException implements ForrstException
{
    public static function fromTo(string $from, string $to, string $reason): self
    {
        return new self(
            sprintf(
                'Invalid status transition from %s to %s. %s',
                $from,
                $to,
                $reason,
            ),
        );
    }
}

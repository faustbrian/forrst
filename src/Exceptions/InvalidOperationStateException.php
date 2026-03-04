<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use function sprintf;

/**
 * Exception thrown when an operation is in an invalid state for the requested action.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidOperationStateException extends ValidationException
{
    public static function forState(string $currentState, string $requiredState): self
    {
        return new self(sprintf('Operation is in state "%s", but "%s" is required', $currentState, $requiredState));
    }
}

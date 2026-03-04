<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Exceptions;

use LogicException;

/**
 * Test exception simulating logic errors.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SimulatedLogicException extends LogicException
{
    /**
     * Create a simulated custom logic error.
     */
    public static function customLogicError(): self
    {
        return new self('Custom logic error');
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Exceptions;

use TypeError;

/**
 * Test exception simulating type errors.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SimulatedTypeError extends TypeError
{
    /**
     * Create a simulated type error.
     */
    public static function typeErrorOccurred(): self
    {
        return new self('Type error occurred');
    }
}

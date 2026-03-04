<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Exceptions;

use RuntimeException;

/**
 * Test exception simulating unexpected runtime errors.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SimulatedRuntimeException extends RuntimeException
{
    /**
     * Create a simulated runtime exception.
     */
    public static function unexpected(): self
    {
        return new self('Unexpected error');
    }

    /**
     * Create a simulated test exception.
     */
    public static function test(): self
    {
        return new self('Test exception');
    }

    /**
     * Create a simulated runtime error.
     */
    public static function runtimeError(): self
    {
        return new self('Runtime error occurred');
    }
}

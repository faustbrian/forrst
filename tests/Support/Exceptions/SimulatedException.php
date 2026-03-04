<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Exceptions;

use Exception;

/**
 * Test exception simulating generic errors.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SimulatedException extends Exception
{
    /**
     * Create a simulated generic exception.
     */
    public static function somethingWentWrong(): self
    {
        return new self('Something went wrong');
    }

    /**
     * Create a simulated test error.
     */
    public static function testError(): self
    {
        return new self('Test error');
    }

    /**
     * Create a simulated unwrapped method failure.
     */
    public static function unwrappedMethodFailed(): self
    {
        return new self('Unwrapped method failed');
    }
}

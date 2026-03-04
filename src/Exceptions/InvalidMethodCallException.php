<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use BadMethodCallException;

use function sprintf;

/**
 * Exception thrown when a method is called in an invalid context.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidMethodCallException extends BadMethodCallException implements ForrstException
{
    public static function cannotCall(string $method, string $reason): self
    {
        return new self(sprintf('Cannot call %s: %s', $method, $reason));
    }
}

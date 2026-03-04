<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use LogicException;

use function sprintf;

/**
 * Exception thrown when a required method has not been implemented.
 * @author Brian Faust <brian@cline.sh>
 */
final class MissingMethodImplementationException extends LogicException implements ForrstException
{
    public static function forMethod(string $class, string $method): self
    {
        return new self(sprintf('Class %s must implement %s()', $class, $method));
    }
}

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
 * Exception thrown when a resolved model is not of the expected type.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidModelException extends RuntimeException implements ForrstException
{
    public static function notInstanceOf(string $expectedClass): self
    {
        return new self(sprintf('Resolved model is not an instance of %s', $expectedClass));
    }
}

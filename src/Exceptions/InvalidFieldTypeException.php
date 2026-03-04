<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use function gettype;
use function sprintf;

/**
 * Exception thrown when a field has an invalid type.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidFieldTypeException extends ValidationException
{
    public static function forField(string $field, string $expectedType, mixed $actualValue): self
    {
        return new self(sprintf(
            '%s must be %s, got %s',
            $field,
            $expectedType,
            gettype($actualValue),
        ));
    }
}

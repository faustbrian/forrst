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
 * Exception thrown when a JSON pointer format is invalid.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidJsonPointerException extends ValidationException
{
    public static function forPointer(string $pointer): self
    {
        return new self(sprintf('Invalid JSON pointer format: %s', $pointer));
    }
}

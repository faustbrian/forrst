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
 * Exception thrown when a function name is invalid.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidFunctionNameException extends ValidationException
{
    public static function forName(string $name, string $reason): self
    {
        return new self(sprintf('Invalid function name "%s": %s', $name, $reason));
    }
}

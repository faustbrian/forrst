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
 * Exception thrown when a field must be positive (greater than zero).
 * @author Brian Faust <brian@cline.sh>
 */
final class MustBePositiveException extends ValidationException
{
    public static function forField(string $field): self
    {
        return new self(sprintf('%s must be positive', $field));
    }
}

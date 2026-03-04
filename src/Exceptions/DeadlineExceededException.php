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
 * Exception thrown when a deadline exceeds the maximum allowed value.
 * @author Brian Faust <brian@cline.sh>
 */
final class DeadlineExceededException extends ValidationException
{
    public static function exceedsMaximum(string $maximum): self
    {
        return new self(sprintf('Deadline cannot exceed %s from now', $maximum));
    }
}

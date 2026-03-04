<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use RuntimeException;
use Throwable;

use function sprintf;

/**
 * Exception thrown when data transformation fails.
 * @author Brian Faust <brian@cline.sh>
 */
final class DataTransformationException extends RuntimeException implements ForrstException
{
    public static function cannotTransform(string $from, string $to, string $reason, ?Throwable $previous = null): self
    {
        return new self(sprintf('Cannot transform %s to %s: %s', $from, $to, $reason), 0, $previous);
    }
}

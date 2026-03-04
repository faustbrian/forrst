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
 * Exception thrown when an arithmetic overflow occurs.
 * @author Brian Faust <brian@cline.sh>
 */
final class OverflowException extends ValidationException
{
    public static function forOperation(string $operation): self
    {
        return new self(sprintf('Integer overflow in %s', $operation));
    }
}

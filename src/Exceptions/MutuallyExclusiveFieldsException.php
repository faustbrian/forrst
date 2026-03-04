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
 * Exception thrown when mutually exclusive fields are both set.
 * @author Brian Faust <brian@cline.sh>
 */
final class MutuallyExclusiveFieldsException extends ValidationException
{
    public static function forFields(string $field1, string $field2): self
    {
        return new self(sprintf('Cannot have both %s and %s', $field1, $field2));
    }
}

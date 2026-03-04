<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use function implode;
use function sprintf;

/**
 * Exception thrown when a value is not one of the allowed enum values.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidEnumValueException extends ValidationException
{
    /**
     * @param array<string> $allowedValues
     */
    public static function forField(string $field, array $allowedValues): self
    {
        return new self(sprintf(
            '%s must be one of: %s',
            $field,
            implode(', ', $allowedValues),
        ));
    }
}

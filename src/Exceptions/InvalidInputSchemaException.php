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
 * Exception thrown when a JSON schema for function input is invalid.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidInputSchemaException extends ValidationException
{
    public static function forField(string $field, string $reason): self
    {
        return new self(sprintf('Invalid input schema for "%s": %s', $field, $reason));
    }
}

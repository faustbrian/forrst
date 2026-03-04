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
 * Exception thrown when an unknown JSON schema type is encountered.
 * @author Brian Faust <brian@cline.sh>
 */
final class UnknownSchemaTypeException extends ValidationException
{
    public static function forType(string $type): self
    {
        return new self(sprintf('Unknown schema type: %s', $type));
    }
}

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
 * Exception thrown when attempting to register a duplicate item.
 * @author Brian Faust <brian@cline.sh>
 */
final class DuplicateRegistrationException extends ValidationException
{
    public static function forItem(string $type, string $name): self
    {
        return new self(sprintf('%s "%s" is already registered', $type, $name));
    }
}

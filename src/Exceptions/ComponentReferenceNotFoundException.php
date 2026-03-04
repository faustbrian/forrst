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
 * Exception thrown when a component reference cannot be resolved.
 * @author Brian Faust <brian@cline.sh>
 */
final class ComponentReferenceNotFoundException extends ValidationException
{
    public static function forRef(string $ref): self
    {
        return new self(sprintf("Component reference '%s' does not exist", $ref));
    }
}

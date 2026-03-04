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
 * Exception thrown when a semantic version string is invalid.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidSemanticVersionException extends ValidationException
{
    public static function forVersion(string $version): self
    {
        return new self(sprintf('Version must follow semantic versioning (e.g., 1.0.0), got: %s', $version));
    }
}

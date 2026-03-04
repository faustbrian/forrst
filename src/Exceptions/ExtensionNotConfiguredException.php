<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use RuntimeException;

use function sprintf;

/**
 * Exception thrown when an extension is used but not configured.
 * @author Brian Faust <brian@cline.sh>
 */
final class ExtensionNotConfiguredException extends RuntimeException implements ForrstException
{
    public static function forExtension(string $extension): self
    {
        return new self(sprintf('Extension "%s" is not configured', $extension));
    }
}

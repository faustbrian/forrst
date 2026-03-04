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
 * Exception thrown when a server fails to boot.
 * @author Brian Faust <brian@cline.sh>
 */
final class ServerBootException extends RuntimeException implements ForrstException
{
    public static function forServer(string $serverName, string $reason): self
    {
        return new self(sprintf('Failed to boot server "%s": %s', $serverName, $reason));
    }
}

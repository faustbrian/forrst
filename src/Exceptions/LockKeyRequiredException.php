<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use InvalidArgumentException;

/**
 * Exception thrown when a lock key is missing or empty.
 *
 * Thrown during atomic lock operations when the lock key parameter
 * is not provided or is an empty string.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/atomic-lock
 */
final class LockKeyRequiredException extends InvalidArgumentException implements RpcException
{
    /**
     * Create exception for missing lock key.
     *
     * @return self New exception instance
     */
    public static function create(): self
    {
        return new self('Lock key is required');
    }
}

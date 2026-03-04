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
 * Exception thrown when a lock TTL is missing.
 *
 * Thrown during atomic lock acquisition when the TTL (time-to-live)
 * parameter is not provided. TTL is required to prevent orphaned locks.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/atomic-lock
 */
final class LockTtlRequiredException extends InvalidArgumentException implements RpcException
{
    /**
     * Create exception for missing lock TTL.
     *
     * @return self New exception instance
     */
    public static function create(): self
    {
        return new self('Lock TTL is required');
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Cline\Forrst\Enums\ErrorCode;

/**
 * Exception thrown when a lock does not exist.
 *
 * Part of the Forrst atomic lock extension exceptions. Thrown when attempting to
 * release or query a lock that does not exist in the server's lock registry.
 * This may occur if the lock was never acquired, has already been released,
 * or has expired due to TTL.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/atomic-lock
 */
final class LockNotFoundException extends NotFoundException
{
    /**
     * Create an exception for a lock that was not found.
     *
     * @param  string $key The lock key that was not found
     * @return self   The constructed exception instance
     */
    public static function forKey(string $key): self
    {
        return self::new(
            code: ErrorCode::LockNotFound,
            message: 'Lock does not exist',
            details: ['key' => $key],
        );
    }
}

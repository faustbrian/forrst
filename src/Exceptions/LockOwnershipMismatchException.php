<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Cline\Forrst\Enums\ErrorCode;
use Override;

/**
 * Exception thrown when a lock release is attempted with the wrong owner.
 *
 * Part of the Forrst atomic lock extension exceptions. Thrown when attempting to
 * release a lock with an owner token that does not match the lock's current owner.
 * This prevents accidental release of locks held by other processes.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/atomic-lock
 */
final class LockOwnershipMismatchException extends AbstractRequestException
{
    /**
     * Create an exception for a lock ownership mismatch.
     *
     * @param  string $key The lock key with mismatched ownership
     * @return self   The constructed exception instance
     */
    public static function forKey(string $key): self
    {
        return self::new(
            code: ErrorCode::LockOwnershipMismatch,
            message: 'Lock is owned by a different process',
            details: ['key' => $key],
        );
    }

    /**
     * Returns the HTTP status code for forbidden errors.
     *
     * @return int always returns 403 (Forbidden)
     */
    #[Override()]
    public function getStatusCode(): int
    {
        return 403;
    }
}

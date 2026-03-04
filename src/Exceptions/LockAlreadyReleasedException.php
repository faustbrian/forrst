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
 * Exception thrown when attempting to release a lock that was already released.
 *
 * Part of the Forrst atomic lock extension exceptions. Thrown when attempting to
 * release a lock that has already been released. This is a conflict condition
 * indicating the release operation cannot be performed.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/atomic-lock
 */
final class LockAlreadyReleasedException extends AbstractRequestException
{
    /**
     * Create an exception for a lock that was already released.
     *
     * @param  string $key The lock key that was already released
     * @return self   The constructed exception instance
     */
    public static function forKey(string $key): self
    {
        return self::new(
            code: ErrorCode::LockAlreadyReleased,
            message: 'Lock was already released',
            details: ['key' => $key],
        );
    }

    /**
     * Returns the HTTP status code for conflict errors.
     *
     * @return int always returns 409 (Conflict)
     */
    #[Override()]
    public function getStatusCode(): int
    {
        return 409;
    }
}

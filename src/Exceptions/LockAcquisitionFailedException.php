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
 * Exception thrown when a lock cannot be acquired immediately.
 *
 * Part of the Forrst atomic lock extension exceptions. Thrown when attempting to
 * acquire a lock without blocking and the lock is already held by another process.
 * Clients should implement exponential backoff and retry.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/atomic-lock
 */
final class LockAcquisitionFailedException extends AbstractRequestException
{
    /**
     * Create an exception for a failed lock acquisition.
     *
     * @param  string $key     The lock key that could not be acquired
     * @param  string $scope   The scope applied (function or global)
     * @param  string $fullKey The full lock key with scope prefix
     * @return self   The constructed exception instance
     */
    public static function forKey(string $key, string $scope, string $fullKey): self
    {
        return self::new(
            code: ErrorCode::LockAcquisitionFailed,
            message: 'Unable to acquire lock',
            details: [
                'key' => $key,
                'scope' => $scope,
                'full_key' => $fullKey,
            ],
        );
    }

    /**
     * Returns the HTTP status code for locked resource errors.
     *
     * @return int always returns 423 (Locked)
     */
    #[Override()]
    public function getStatusCode(): int
    {
        return 423;
    }
}

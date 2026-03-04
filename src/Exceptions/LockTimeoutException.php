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

use function sprintf;

/**
 * Exception thrown when a blocking lock acquisition times out.
 *
 * Part of the Forrst atomic lock extension exceptions. Thrown when attempting to
 * acquire a lock with blocking and the timeout expires before the lock becomes
 * available. Clients should implement exponential backoff and retry.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/atomic-lock
 */
final class LockTimeoutException extends AbstractRequestException
{
    /**
     * Create an exception for a lock acquisition timeout.
     *
     * @param  string               $key     The lock key that timed out
     * @param  string               $scope   The scope applied (function or global)
     * @param  string               $fullKey The full lock key with scope prefix
     * @param  array<string, mixed> $waited  Duration waited before timeout
     * @return self                 The constructed exception instance
     */
    public static function forKey(string $key, string $scope, string $fullKey, array $waited): self
    {
        return self::new(
            code: ErrorCode::LockTimeout,
            // @phpstan-ignore-next-line argument.type
            message: sprintf('Lock acquisition timed out after %s %ss', $waited['value'], $waited['unit']),
            details: [
                'key' => $key,
                'scope' => $scope,
                'full_key' => $fullKey,
                'waited' => $waited,
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

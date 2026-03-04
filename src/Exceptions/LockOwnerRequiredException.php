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
 * Exception thrown when a lock owner is missing or empty.
 *
 * Thrown during lock release operations when the owner parameter
 * is not provided or is an empty string. The owner is required to
 * verify ownership before releasing a lock.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/atomic-lock
 */
final class LockOwnerRequiredException extends InvalidArgumentException implements RpcException
{
    /**
     * Create exception for missing lock owner.
     *
     * @return self New exception instance
     */
    public static function create(): self
    {
        return new self('Lock owner is required');
    }
}

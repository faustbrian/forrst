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
 * Exception thrown when a user exceeds their concurrent async operation limit.
 *
 * Prevents resource exhaustion by limiting the number of active operations
 * per user. Users must wait for existing operations to complete before
 * creating new ones.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/async
 */
final class OperationQuotaExceededException extends AbstractRequestException
{
    /**
     * Create an exception for exceeding concurrent operation limit.
     *
     * @param int $current Current number of active operations
     * @param int $limit   Maximum allowed concurrent operations
     *
     * @return self The constructed exception instance
     */
    public static function create(int $current, int $limit): self
    {
        return self::new(
            code: ErrorCode::AsyncQuotaExceeded,
            message: 'Maximum concurrent async operations exceeded',
            details: [
                'current_active' => $current,
                'limit' => $limit,
            ],
        );
    }

    /**
     * Returns the HTTP status code for quota errors.
     *
     * @return int always returns 429 (Too Many Requests)
     */
    #[Override()]
    public function getStatusCode(): int
    {
        return 429;
    }
}

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
 * Exception thrown when an operation update fails due to version mismatch.
 *
 * Indicates the operation was modified by another process between read and write.
 * Callers should re-fetch the operation and retry the update, or fail gracefully.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/async
 */
final class OperationVersionConflictException extends AbstractRequestException
{
    /**
     * Create an exception for a version conflict on operation update.
     *
     * @param string $operationId     The operation that had a conflict
     * @param int    $expectedVersion The version expected by the caller
     *
     * @return self The constructed exception instance
     */
    public static function create(string $operationId, int $expectedVersion): self
    {
        return self::new(
            code: ErrorCode::AsyncVersionConflict,
            message: 'Operation was modified by another process',
            details: [
                'operation_id' => $operationId,
                'expected_version' => $expectedVersion,
            ],
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

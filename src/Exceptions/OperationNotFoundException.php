<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Cline\Forrst\Enums\ErrorCode;

use function sprintf;

/**
 * Exception thrown when a requested async operation cannot be found.
 *
 * Represents Forrst error code ASYNC_OPERATION_NOT_FOUND with HTTP 404 status.
 * This exception is raised when attempting to query, cancel, or interact with
 * an operation that doesn't exist or has expired from the operation store.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/extensions/async
 * @see https://docs.cline.sh/forrst/errors
 */
final class OperationNotFoundException extends NotFoundException
{
    /**
     * Creates a not found exception for an async operation.
     *
     * @param  string $operationId Unique identifier of the operation that could not be found
     * @return self   a new instance with ASYNC_OPERATION_NOT_FOUND code, HTTP 404
     *                status, and the operation identifier in error details
     */
    public static function create(string $operationId): self
    {
        return self::new(
            code: ErrorCode::AsyncOperationNotFound,
            message: sprintf("Operation '%s' not found", $operationId),
            details: [
                'operation_id' => $operationId,
            ],
        );
    }
}

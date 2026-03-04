<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Cline\Forrst\Data\OperationStatus;
use Cline\Forrst\Enums\ErrorCode;

use function sprintf;

/**
 * Exception thrown when an async operation cannot be cancelled due to its current state.
 *
 * Represents Forrst error code ASYNC_CANNOT_CANCEL. This exception is raised when a
 * cancellation request targets an operation that is not in a cancellable state,
 * such as operations that are already completed, failed, or cancelled.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/extensions/async
 * @see https://docs.cline.sh/forrst/extensions/cancellation
 * @see https://docs.cline.sh/forrst/errors
 */
final class OperationCannotCancelException extends OperationException
{
    /**
     * Creates an exception for an operation that cannot be cancelled.
     *
     * @param  string          $operationId Unique identifier of the operation that cannot
     *                                      be cancelled
     * @param  OperationStatus $status      Current status of the operation preventing
     *                                      cancellation (e.g., completed, failed)
     * @return self            a new instance with ASYNC_CANNOT_CANCEL code and
     *                         details about the operation state
     */
    public static function create(string $operationId, OperationStatus $status): self
    {
        return self::new(
            code: ErrorCode::AsyncCannotCancel,
            message: sprintf("Operation '%s' cannot be cancelled (status: %s)", $operationId, $status->value),
            details: [
                'operation_id' => $operationId,
                'current_status' => $status->value,
            ],
        );
    }
}

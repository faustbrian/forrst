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
 * Exception thrown when an async operation execution fails.
 *
 * Represents Forrst error code ASYNC_OPERATION_FAILED. This exception is raised
 * when an operation encounters an error during execution and transitions to
 * a failed state, providing context about the failure reason.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/extensions/async
 * @see https://docs.cline.sh/forrst/errors
 */
final class OperationFailedException extends OperationException
{
    /**
     * Creates an exception for a failed operation.
     *
     * @param  string      $operationId Unique identifier of the operation that failed
     * @param  null|string $reason      Optional detailed description of why the operation
     *                                  failed. If null, a generic failure message is used.
     * @return self        a new instance with ASYNC_OPERATION_FAILED code and
     *                     failure details
     */
    public static function create(string $operationId, ?string $reason = null): self
    {
        return self::new(
            code: ErrorCode::AsyncOperationFailed,
            message: $reason ?? sprintf("Operation '%s' failed", $operationId),
            details: [
                'operation_id' => $operationId,
            ],
        );
    }
}

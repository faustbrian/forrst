<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions\Async\Descriptors;

use Cline\Forrst\Contracts\DescriptorInterface;
use Cline\Forrst\Discovery\FunctionDescriptor;
use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Functions\FunctionUrn;

/**
 * Descriptor for the operation cancel function.
 *
 * Defines discovery metadata for the forrst.operation.cancel system function.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class OperationCancelDescriptor implements DescriptorInterface
{
    public static function create(): FunctionDescriptor
    {
        return FunctionDescriptor::make()
            ->urn(FunctionUrn::OperationCancel)
            ->summary('Cancel a pending async operation')
            ->security([
                'authentication' => 'required',
                'authorization' => 'owner_only',
                'scope' => 'operations:cancel',
            ])
            ->argument(
                name: 'operation_id',
                schema: [
                    'type' => 'string',
                    'pattern' => '^op_[a-f0-9]{24}$',
                    'minLength' => 27,
                    'maxLength' => 27,
                    'description' => 'Operation identifier (format: op_ followed by 24 hex characters)',
                ],
                required: true,
                description: 'Unique operation identifier',
            )
            ->result(
                schema: [
                    'type' => 'object',
                    'properties' => [
                        'operation_id' => [
                            'type' => 'string',
                            'description' => 'Operation ID',
                        ],
                        'status' => [
                            'type' => 'string',
                            'const' => 'cancelled',
                            'description' => 'New status',
                        ],
                        'cancelled_at' => [
                            'type' => 'string',
                            'format' => 'date-time',
                            'description' => 'Cancellation timestamp',
                        ],
                    ],
                    'required' => ['operation_id', 'status', 'cancelled_at'],
                ],
                description: 'Operation cancellation response',
            )
            ->error(
                code: ErrorCode::AsyncOperationNotFound,
                message: 'Operation not found',
                description: 'The specified operation ID does not exist',
            )
            ->error(
                code: ErrorCode::AsyncCannotCancel,
                message: 'Operation cannot be cancelled',
                description: 'The operation has already completed or cannot be cancelled',
            );
    }
}

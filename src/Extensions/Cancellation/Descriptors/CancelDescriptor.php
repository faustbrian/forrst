<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions\Cancellation\Descriptors;

use Cline\Forrst\Contracts\DescriptorInterface;
use Cline\Forrst\Discovery\FunctionDescriptor;
use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Functions\FunctionUrn;

/**
 * Descriptor for the cancel function.
 *
 * Defines discovery metadata for the forrst.cancel system function.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CancelDescriptor implements DescriptorInterface
{
    public static function create(): FunctionDescriptor
    {
        return FunctionDescriptor::make()
            ->urn(FunctionUrn::Cancel)
            ->summary('Cancel a request by its cancellation token')
            ->argument(
                name: 'token',
                schema: ['type' => 'string'],
                required: true,
                description: 'Cancellation token from original request',
            )
            ->result(
                schema: [
                    'type' => 'object',
                    'properties' => [
                        'cancelled' => [
                            'type' => 'boolean',
                            'description' => 'Whether cancellation was successful',
                        ],
                        'token' => [
                            'type' => 'string',
                            'description' => 'The cancellation token',
                        ],
                    ],
                    'required' => ['cancelled', 'token'],
                ],
                description: 'Cancellation result',
            )
            ->error(
                code: ErrorCode::CancellationTokenUnknown,
                message: 'Unknown cancellation token',
                description: 'The specified cancellation token does not exist or has expired',
            )
            ->error(
                code: ErrorCode::CancellationTooLate,
                message: 'Request already completed',
                description: 'The request has already completed and cannot be cancelled',
            );
    }
}

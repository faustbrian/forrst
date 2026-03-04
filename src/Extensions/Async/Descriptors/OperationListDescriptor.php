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
use Cline\Forrst\Functions\FunctionUrn;

/**
 * Descriptor for the operation list function.
 *
 * Defines discovery metadata for the forrst.operation.list system function.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class OperationListDescriptor implements DescriptorInterface
{
    public static function create(): FunctionDescriptor
    {
        return FunctionDescriptor::make()
            ->urn(FunctionUrn::OperationList)
            ->summary('List operations for the current caller')
            ->security([
                'authentication' => 'required',
                'authorization' => 'owner_only',
                'scope' => 'operations:read',
            ])
            ->argument(
                name: 'status',
                schema: [
                    'type' => 'string',
                    'enum' => ['pending', 'processing', 'completed', 'failed', 'cancelled'],
                ],
                required: false,
                description: 'Filter by status',
            )
            ->argument(
                name: 'function',
                schema: ['type' => 'string'],
                required: false,
                description: 'Filter by function name',
            )
            ->argument(
                name: 'limit',
                schema: [
                    'type' => 'integer',
                    'default' => 50,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
                required: false,
                description: 'Max results (default 50)',
            )
            ->argument(
                name: 'cursor',
                schema: [
                    'type' => 'string',
                    'pattern' => '^[a-zA-Z0-9+/=_-]+$',
                    'description' => 'Opaque pagination cursor from previous response',
                ],
                required: false,
                description: 'Pagination cursor for retrieving next page',
            )
            ->result(
                schema: [
                    'type' => 'object',
                    'properties' => [
                        'operations' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => [
                                        'type' => 'string',
                                        'pattern' => '^op_[a-f0-9]{24}$',
                                        'description' => 'Unique operation identifier',
                                    ],
                                    'function' => [
                                        'type' => 'string',
                                        'description' => 'Function URN that was called',
                                    ],
                                    'version' => [
                                        'type' => 'string',
                                        'pattern' => '^\d+$',
                                        'description' => 'Function version',
                                    ],
                                    'status' => [
                                        'type' => 'string',
                                        'enum' => ['pending', 'processing', 'completed', 'failed', 'cancelled'],
                                        'description' => 'Current operation status',
                                    ],
                                    'progress' => [
                                        'type' => 'number',
                                        'minimum' => 0.0,
                                        'maximum' => 1.0,
                                        'description' => 'Progress percentage (0-1)',
                                    ],
                                    'started_at' => [
                                        'type' => ['string', 'null'],
                                        'format' => 'date-time',
                                        'description' => 'When operation started processing',
                                    ],
                                ],
                                'required' => ['id', 'function', 'version', 'status'],
                            ],
                            'description' => 'List of operations matching filter criteria',
                        ],
                        'next_cursor' => [
                            'type' => 'string',
                            'description' => 'Opaque cursor for fetching next page',
                        ],
                    ],
                    'required' => ['operations'],
                    'additionalProperties' => false,
                ],
                description: 'Paginated operation list response',
            );
    }
}

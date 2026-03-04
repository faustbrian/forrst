<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions\Diagnostics\Descriptors;

use Cline\Forrst\Contracts\DescriptorInterface;
use Cline\Forrst\Discovery\FunctionDescriptor;
use Cline\Forrst\Functions\FunctionUrn;

/**
 * Descriptor for the ping function.
 *
 * Defines discovery metadata for the forrst.ping system function.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PingDescriptor implements DescriptorInterface
{
    public static function create(): FunctionDescriptor
    {
        return FunctionDescriptor::make()
            ->urn(FunctionUrn::Ping)
            ->summary('Simple connectivity check')
            ->result(
                schema: [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type' => 'string',
                            'enum' => ['healthy', 'degraded', 'unhealthy'],
                            'description' => 'Health status',
                        ],
                        'timestamp' => [
                            'type' => 'string',
                            'format' => 'date-time',
                            'description' => 'ISO 8601 timestamp',
                        ],
                        'details' => [
                            'type' => 'object',
                            'description' => 'Optional additional health info',
                        ],
                    ],
                    'required' => ['status', 'timestamp'],
                ],
                description: 'Ping response with health status',
            );
    }
}

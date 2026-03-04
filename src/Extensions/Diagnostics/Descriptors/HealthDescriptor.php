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
 * Descriptor for the health function.
 *
 * Defines discovery metadata for the forrst.health system function.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class HealthDescriptor implements DescriptorInterface
{
    public static function create(): FunctionDescriptor
    {
        return FunctionDescriptor::make()
            ->urn(FunctionUrn::Health)
            ->summary('Comprehensive health check with component-level status')
            ->argument(
                name: 'component',
                schema: [
                    'type' => 'string',
                    'description' => 'Specific component to check. Use "self" for basic server ping without running component checks.',
                ],
                required: false,
                description: 'Check specific component only (use "self" for basic ping)',
            )
            ->argument(
                name: 'include_details',
                schema: ['type' => 'boolean', 'default' => true],
                required: false,
                description: 'Include detailed check info (default: true)',
            )
            ->result(
                schema: [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type' => 'string',
                            'enum' => ['healthy', 'degraded', 'unhealthy'],
                            'description' => 'Aggregate health status',
                        ],
                        'components' => [
                            'type' => 'object',
                            'description' => 'Component health status',
                        ],
                        'functions' => [
                            'type' => 'object',
                            'description' => 'Function-level health (optional)',
                        ],
                        'timestamp' => [
                            'type' => 'string',
                            'format' => 'date-time',
                            'description' => 'ISO 8601 timestamp',
                        ],
                        'version' => [
                            'type' => 'string',
                            'description' => 'Optional service version',
                        ],
                    ],
                    'required' => ['status', 'timestamp'],
                ],
                description: 'Health check response with component status',
            );
    }
}

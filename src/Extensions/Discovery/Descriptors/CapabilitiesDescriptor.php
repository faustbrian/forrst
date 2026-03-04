<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions\Discovery\Descriptors;

use Cline\Forrst\Contracts\DescriptorInterface;
use Cline\Forrst\Discovery\FunctionDescriptor;
use Cline\Forrst\Functions\FunctionUrn;

/**
 * Descriptor for the capabilities function.
 *
 * Defines discovery metadata for the forrst.capabilities system function.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CapabilitiesDescriptor implements DescriptorInterface
{
    public static function create(): FunctionDescriptor
    {
        return FunctionDescriptor::make()
            ->urn(FunctionUrn::Capabilities)
            ->summary('Discover service capabilities and supported features')
            ->result(
                schema: [
                    'type' => 'object',
                    'properties' => [
                        'service' => [
                            'type' => 'string',
                            'description' => 'Service identifier',
                        ],
                        'protocolVersions' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Supported Forrst protocol versions',
                        ],
                        'extensions' => [
                            'type' => 'array',
                            'description' => 'Supported extensions',
                        ],
                        'functions' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Available function names',
                        ],
                        'limits' => [
                            'type' => 'object',
                            'description' => 'Service limits',
                        ],
                    ],
                    'required' => ['service', 'protocolVersions', 'functions'],
                ],
                description: 'Service capabilities response',
            );
    }
}

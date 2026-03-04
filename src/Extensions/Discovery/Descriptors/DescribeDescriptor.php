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
 * Descriptor for the describe function.
 *
 * Defines discovery metadata for the forrst.describe system function.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DescribeDescriptor implements DescriptorInterface
{
    public static function create(): FunctionDescriptor
    {
        return FunctionDescriptor::make()
            ->urn(FunctionUrn::Describe)
            ->summary('Returns the Forrst Discovery document describing this service')
            ->argument(
                name: 'function',
                schema: ['type' => 'string'],
                required: false,
                description: 'Specific function to describe',
            )
            ->argument(
                name: 'version',
                schema: ['type' => 'string'],
                required: false,
                description: 'Specific version (with function)',
            )
            ->result(
                schema: [
                    'type' => 'object',
                    'description' => 'Forrst Discovery Document',
                ],
                description: 'Complete discovery document or single function descriptor',
            );
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions\Discovery\Functions;

use Cline\Forrst\Attributes\Descriptor;
use Cline\Forrst\Contracts\FunctionInterface;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Extensions\Discovery\Descriptors\CapabilitiesDescriptor;
use Cline\Forrst\Functions\AbstractFunction;

use function array_map;

/**
 * Service capabilities discovery function.
 *
 * Implements forrst.capabilities for discovering what the service supports.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/system-functions
 */
#[Descriptor(CapabilitiesDescriptor::class)]
final class CapabilitiesFunction extends AbstractFunction
{
    /**
     * Create a new capabilities function instance.
     *
     * @param string                           $serviceName Service identifier
     * @param array<int, FunctionInterface>    $functions   Registered functions
     * @param array<int, array<string, mixed>> $extensions  Supported extensions
     * @param array<string, mixed>             $limits      Service limits
     */
    public function __construct(
        private readonly string $serviceName,
        private readonly array $functions = [],
        private readonly array $extensions = [],
        private readonly array $limits = [],
    ) {}

    /**
     * Execute the capabilities function.
     *
     * @return array<string, mixed> Service capabilities
     */
    public function __invoke(): array
    {
        $functionNames = array_map(
            fn (FunctionInterface $fn): string => $fn->getUrn(),
            $this->functions,
        );

        $response = [
            'service' => $this->serviceName,
            'protocolVersions' => [ProtocolData::VERSION],
            'functions' => $functionNames,
        ];

        if ($this->extensions !== []) {
            $response['extensions'] = $this->extensions;
        }

        if ($this->limits !== []) {
            $response['limits'] = $this->limits;
        }

        return $response;
    }
}

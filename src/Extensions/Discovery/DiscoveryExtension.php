<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions\Discovery;

use Cline\Forrst\Contracts\FunctionInterface;
use Cline\Forrst\Contracts\ProvidesFunctionsInterface;
use Cline\Forrst\Extensions\AbstractExtension;
use Cline\Forrst\Extensions\Discovery\Functions\CapabilitiesFunction;
use Cline\Forrst\Extensions\Discovery\Functions\DescribeFunction;
use Cline\Forrst\Extensions\ExtensionUrn;
use Override;

/**
 * Discovery extension handler.
 *
 * Provides service introspection functions that enable clients to discover
 * what a server supports. This includes lightweight capability checks and
 * full schema introspection via the Forrst Discovery document.
 *
 * Functions provided:
 * - capabilities: Lightweight capability summary
 * - describe: Full service schema introspection
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/discovery
 */
final class DiscoveryExtension extends AbstractExtension implements ProvidesFunctionsInterface
{
    /**
     * Get the functions provided by this extension.
     *
     * @return array<int, class-string<FunctionInterface>>
     */
    #[Override()]
    public function functions(): array
    {
        return [
            CapabilitiesFunction::class,
            DescribeFunction::class,
        ];
    }

    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function getUrn(): string
    {
        return ExtensionUrn::Discovery->value;
    }

    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function getSubscribedEvents(): array
    {
        return [];
    }
}

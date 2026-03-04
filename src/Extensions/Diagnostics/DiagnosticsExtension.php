<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions\Diagnostics;

use Cline\Forrst\Contracts\FunctionInterface;
use Cline\Forrst\Contracts\ProvidesFunctionsInterface;
use Cline\Forrst\Extensions\AbstractExtension;
use Cline\Forrst\Extensions\Diagnostics\Functions\HealthFunction;
use Cline\Forrst\Extensions\Diagnostics\Functions\PingFunction;
use Cline\Forrst\Extensions\ExtensionUrn;
use Override;

/**
 * Diagnostics extension handler.
 *
 * Provides health monitoring functions for service availability and
 * component-level health checks. Enables load balancers, monitoring
 * systems, and clients to verify service status.
 *
 * Functions provided:
 * - ping: Simple connectivity check
 * - health: Comprehensive component health
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/diagnostics
 */
final class DiagnosticsExtension extends AbstractExtension implements ProvidesFunctionsInterface
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
            PingFunction::class,
            HealthFunction::class,
        ];
    }

    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function getUrn(): string
    {
        return ExtensionUrn::Diagnostics->value;
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

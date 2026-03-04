<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fakes;

use Cline\Forrst\Extensions\Async\AsyncExtension;
use Cline\Forrst\Extensions\AtomicLock\AtomicLockExtension;
use Cline\Forrst\Extensions\Cancellation\CancellationExtension;
use Cline\Forrst\Extensions\Diagnostics\DiagnosticsExtension;
use Cline\Forrst\Extensions\Discovery\Functions\CapabilitiesFunction;
use Cline\Forrst\Servers\AbstractServer;
use Illuminate\Support\Facades\App;
use Override;

/**
 * Test server with all system extensions enabled.
 *
 * Used for testing discovery and system functions.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FullServer extends AbstractServer
{
    #[Override()]
    public function getRoutePath(): string
    {
        return '/rpc';
    }

    #[Override()]
    public function getRouteName(): string
    {
        return 'rpc';
    }

    #[Override()]
    public function functions(): array
    {
        // CapabilitiesFunction is from DiscoveryExtension but we can't use
        // DiscoveryExtension because it also provides DescribeFunction
        // which is auto-registered by AbstractServer
        return [
            CapabilitiesFunction::class,
        ];
    }

    #[Override()]
    public function extensions(): array
    {
        return [
            App::make(AsyncExtension::class),
            new AtomicLockExtension(),
            new CancellationExtension(),
            new DiagnosticsExtension(),
        ];
    }

    #[Override()]
    public function validate(): void
    {
        // No validation needed for test fake
    }
}

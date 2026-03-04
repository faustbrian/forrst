<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions\Diagnostics\Functions;

use Carbon\CarbonImmutable;
use Cline\Forrst\Attributes\Descriptor;
use Cline\Forrst\Enums\HealthStatus;
use Cline\Forrst\Extensions\Diagnostics\Descriptors\PingDescriptor;
use Cline\Forrst\Functions\AbstractFunction;

/**
 * System health check function.
 *
 * Implements forrst.ping for simple connectivity checks and service availability
 * verification. This is a lightweight alternative to forrst.health for basic
 * health monitoring that returns immediately with minimal overhead.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/system-functions
 */
#[Descriptor(PingDescriptor::class)]
final class PingFunction extends AbstractFunction
{
    /**
     * Execute the ping function.
     *
     * Returns an immediate response confirming service availability with
     * current ISO 8601 timestamp. Always returns healthy status.
     *
     * @return array{status: string, timestamp: string} Ping response with
     *                                                  'healthy' status and current timestamp
     */
    public function __invoke(): array
    {
        return [
            'status' => HealthStatus::Healthy->value,
            'timestamp' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}

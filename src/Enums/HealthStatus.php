<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Enums;

/**
 * Health status values for component and system health checks.
 *
 * Represents the operational status of system components, services, or the
 * overall system health. Used by the diagnostics extension to report the
 * current state of various health check targets.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/diagnostics
 */
enum HealthStatus: string
{
    /**
     * Component is fully operational with no issues.
     *
     * Indicates the component is functioning normally with all checks passing.
     * This is the desired state for all system components.
     */
    case Healthy = 'healthy';

    /**
     * Component is operational but with reduced functionality or performance.
     *
     * Indicates the component is working but experiencing non-critical issues
     * such as high latency, resource constraints, or partial service availability.
     * The system remains functional but may not meet performance SLAs.
     */
    case Degraded = 'degraded';

    /**
     * Component is not operational or experiencing critical failures.
     *
     * Indicates the component has failed health checks and is not functioning
     * properly. This state requires immediate attention as it may impact
     * system functionality or availability.
     */
    case Unhealthy = 'unhealthy';

    /**
     * Get the severity level of this health status.
     *
     * Returns a numeric severity value where lower numbers indicate better health.
     * Used for aggregating multiple component statuses to determine overall system
     * health by selecting the worst (highest severity) status.
     *
     * @return int Severity level (0=healthy, 1=degraded, 2=unhealthy)
     */
    public function severity(): int
    {
        return match ($this) {
            self::Healthy => 0,
            self::Degraded => 1,
            self::Unhealthy => 2,
        };
    }
}

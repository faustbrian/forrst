<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Contracts;

use Cline\Forrst\Data\HealthStatus;

/**
 * Forrst health checker contract interface.
 *
 * Defines the contract for implementing health check monitors for system
 * components and dependencies. Health checkers provide standardized status
 * reporting used by the system.health function.
 *
 * Health checkers are responsible for checking the health of a specific
 * component (database, cache, queue, external API, etc.) and returning
 * a standardized health status. Each checker monitors a single component
 * and reports its availability and performance metrics.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/system-functions Health check specification
 */
interface HealthCheckerInterface
{
    /**
     * Get the component name.
     *
     * Standard component names:
     * - "self" - The service process itself
     * - "database" - Primary database
     * - "cache" - Caching layer (Redis, Memcached)
     * - "queue" - Message queue
     * - "storage" - Object/file storage
     * - "search" - Search engine (Elasticsearch)
     * - "<service>_api" - External service dependency
     *
     * @return string The component identifier
     */
    public function getName(): string;

    /**
     * Check the component health.
     *
     * Performs a health check on the component and returns standardized status
     * information including availability, latency metrics, and diagnostic messages.
     *
     * PERFORMANCE: This method should complete within 5 seconds maximum.
     * Use timeouts when checking external dependencies:
     * - Database queries: 2-3 second timeout
     * - HTTP requests: 3-5 second timeout
     * - Cache operations: 1-2 second timeout
     *
     * If a check times out, return 'unhealthy' with appropriate message
     * rather than throwing an exception.
     *
     * SECURITY: Messages should be informative but must NOT include:
     * - Database connection strings or credentials
     * - Internal IP addresses or hostnames (use generic identifiers)
     * - Stack traces or sensitive error details
     * - Version numbers that could aid attackers
     *
     * Good: "Database connection failed"
     * Bad: "Failed to connect to mysql://user:pass@10.0.1.50:3306/prod_db"
     *
     * @return HealthStatus Health status object
     */
    public function check(): HealthStatus;
}

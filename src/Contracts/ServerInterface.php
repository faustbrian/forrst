<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Contracts;

use Cline\Forrst\Extensions\ExtensionRegistry;
use Cline\Forrst\Repositories\FunctionRepository;
use InvalidArgumentException;

/**
 * Forrst server contract interface.
 *
 * Defines the contract for implementing Forrst server instances that manage RPC
 * endpoints. Servers encapsulate routing, function registration, extension
 * management, and middleware configuration for Forrst API endpoints.
 *
 * Defines the interface for server instances that manage Forrst endpoints,
 * including route configuration, function registration, middleware stacks, and
 * discovery schema definitions. Each server represents a distinct Forrst endpoint
 * with its own configuration, version, and set of available functions.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/ Main documentation
 * @see https://docs.cline.sh/forrst/protocol Protocol specification
 */
interface ServerInterface
{
    /**
     * Get the unique server name identifier.
     *
     * Used to distinguish between multiple server instances in multi-server
     * configurations. Should be a unique, descriptive name for the server.
     *
     * @return string Server name identifier
     */
    public function getName(): string;

    /**
     * Get the HTTP route path for this server endpoint.
     *
     * Returns the URL path where this server accepts Forrst requests.
     * Should include leading slash (e.g., "/api/v1/forrst", "/forrst").
     *
     * @return string HTTP route path
     */
    public function getRoutePath(): string;

    /**
     * Get the Laravel route name for this server endpoint.
     *
     * Returns the named route identifier used for URL generation and
     * route resolution within Laravel's routing system.
     *
     * @return string Laravel route name
     */
    public function getRouteName(): string;

    /**
     * Get the API version identifier for this server.
     *
     * Version string used for API versioning and backwards compatibility
     * tracking. Should follow semantic versioning (e.g., "1.0.0", "2.1.0").
     *
     * @return string API version string
     */
    public function getVersion(): string;

    /**
     * Get the middleware stack for this server endpoint.
     *
     * Returns an array of middleware class names or aliases that should be
     * applied to all requests to this server, in the order they should execute.
     *
     * @return array<int, string> Ordered array of middleware names
     */
    public function getMiddleware(): array;

    /**
     * Get the function repository containing all registered functions.
     *
     * Returns the repository instance that manages function discovery,
     * registration, and dispatching for this server's available functions.
     *
     * @return FunctionRepository Function repository instance
     */
    public function getFunctionRepository(): FunctionRepository;

    /**
     * Get the extension registry containing all registered extensions.
     *
     * Returns the registry instance that manages extension handlers for this
     * server, including lifecycle event dispatching and extension configuration.
     *
     * @return ExtensionRegistry Extension registry instance
     */
    public function getExtensionRegistry(): ExtensionRegistry;

    /**
     * Validate server configuration.
     *
     * Called during server registration to ensure proper setup.
     *
     * @throws InvalidArgumentException If configuration is invalid
     */
    public function validate(): void;
}

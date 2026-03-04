<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Servers;

use Cline\Forrst\Contracts\ExtensionInterface;
use Cline\Forrst\Contracts\FunctionInterface;
use Cline\Forrst\Contracts\ProvidesFunctionsInterface;
use Cline\Forrst\Contracts\ServerInterface;
use Cline\Forrst\Extensions\Discovery\Functions\DescribeFunction;
use Cline\Forrst\Extensions\ExtensionRegistry;
use Cline\Forrst\Http\Middleware\BootServer;
use Cline\Forrst\Http\Middleware\ForceJson;
use Cline\Forrst\Repositories\FunctionRepository;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Override;

use function assert;
use function is_string;

/**
 * Base server class for Forrst endpoints.
 *
 * Provides default configuration and initialization for Forrst servers including
 * function registration, middleware setup, extension management, and route definitions.
 * Concrete server classes extend this to define their specific functions, extensions,
 * and configuration overrides.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/protocol
 */
abstract class AbstractServer implements ServerInterface
{
    /**
     * Repository managing all registered functions for this server.
     *
     * Stores and provides access to all function implementations available on this
     * server instance, including both user-defined and system functions.
     */
    private readonly FunctionRepository $functionRepository;

    /**
     * Registry managing all registered extensions for this server.
     *
     * Handles extension registration and lifecycle for cross-cutting functionality
     * like caching, idempotency, and streaming support.
     */
    private readonly ExtensionRegistry $extensionRegistry;

    /**
     * Initialize the server and register functions and extensions.
     *
     * Creates a function repository with the server's defined functions and automatically
     * registers the system-level forrst.describe function for service discovery. Also
     * initializes the extension registry, registers all server extensions, and auto-registers
     * any functions provided by extensions implementing ProvidesFunctionsInterface.
     */
    public function __construct()
    {
        $this->functionRepository = new FunctionRepository($this->functions());
        $this->functionRepository->register(DescribeFunction::class);

        $this->extensionRegistry = new ExtensionRegistry();

        foreach ($this->extensions() as $extension) {
            $this->extensionRegistry->register($extension);

            // Auto-register functions provided by extensions
            if (!$extension instanceof ProvidesFunctionsInterface) {
                continue;
            }

            foreach ($extension->functions() as $functionClass) {
                $this->functionRepository->register($functionClass);
            }
        }
    }

    /**
     * Get the server name for identification and documentation.
     *
     * Returns the application name from Laravel configuration by default.
     * Override this method to provide a custom server name.
     *
     * @return string Server name from application configuration
     */
    #[Override()]
    public function getName(): string
    {
        $name = Config::get('app.name');
        assert(is_string($name));

        return $name;
    }

    /**
     * Get the URL path where this Forrst server is mounted.
     *
     * Defines the route path for incoming Forrst requests. Override this method
     * to customize the endpoint URL path.
     *
     * @return string URL path for the Forrst endpoint (default: "/forrst")
     */
    #[Override()]
    public function getRoutePath(): string
    {
        return '/forrst';
    }

    /**
     * Get the Laravel route name for this Forrst endpoint.
     *
     * Defines the named route identifier used in Laravel's routing system.
     * Override this method to customize the route name.
     *
     * @return string Laravel route name (default: "forrst")
     */
    #[Override()]
    public function getRouteName(): string
    {
        return 'forrst';
    }

    /**
     * Get the API version for this Forrst server.
     *
     * Returns the semantic version string used in discovery documentation and
     * API versioning. Override this method to specify a custom version.
     *
     * @return string Semantic version string (default: "1.0.0")
     */
    #[Override()]
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Get the middleware stack applied to Forrst requests.
     *
     * Returns an array of middleware class names that process requests before they
     * reach the Forrst endpoints. Default middleware includes JSON content-type enforcement
     * and server bootstrapping. Override to customize the middleware stack.
     *
     * @return array<int, class-string> Array of middleware class names
     */
    #[Override()]
    public function getMiddleware(): array
    {
        return [
            ForceJson::class,
            BootServer::class,
        ];
    }

    /**
     * Get the function repository containing all registered functions.
     *
     * Provides access to the server's function registry for request routing and
     * function invocation. The repository handles function lookup and execution.
     *
     * @return FunctionRepository Repository managing registered functions
     */
    #[Override()]
    public function getFunctionRepository(): FunctionRepository
    {
        return $this->functionRepository;
    }

    /**
     * Get the extension registry containing all registered extensions.
     *
     * Provides access to the server's extension registry for extension lookup
     * and management.
     *
     * @return ExtensionRegistry Registry managing registered extensions
     */
    #[Override()]
    public function getExtensionRegistry(): ExtensionRegistry
    {
        return $this->extensionRegistry;
    }

    /**
     * Define the extensions available on this server.
     *
     * Returns an array of extension instances that implement ExtensionInterface.
     * Extensions provide cross-cutting functionality like caching, idempotency,
     * streaming, and async processing. Override to add server-specific extensions.
     *
     * @return array<int, ExtensionInterface> Array of extension instances to register
     */
    public function extensions(): array
    {
        return [];
    }

    /**
     * Validate server configuration.
     *
     * Override this method to add custom validation logic for your server.
     * Called during server initialization to ensure the server is properly
     * configured before accepting requests.
     *
     * @throws InvalidArgumentException If configuration is invalid
     */
    public function validate(): void
    {
        // Default implementation - no validation required
    }

    /**
     * Define the functions available on this server.
     *
     * Returns an array of function class names that implement the FunctionInterface.
     * Each function represents a callable function that clients can invoke.
     *
     * @return array<int, class-string<FunctionInterface>> Array of function class names
     */
    abstract public function functions(): array;
}

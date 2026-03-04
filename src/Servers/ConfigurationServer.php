<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Servers;

use Cline\Forrst\Contracts\FunctionInterface;
use Cline\Forrst\Data\Configuration\ServerData;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Override;

use function class_exists;
use function class_implements;
use function collect;
use function in_array;
use function mb_rtrim;
use function str_replace;

/**
 * Configuration-driven Forrst server implementation.
 *
 * Creates RPC servers dynamically from configuration data instead of requiring
 * concrete server classes. Automatically discovers and registers functions from
 * configured directories or explicit function lists, enabling flexible server
 * deployment through configuration files.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/protocol
 */
final class ConfigurationServer extends AbstractServer
{
    /**
     * Create a new configuration-based server instance.
     *
     * @param ServerData $server             Configuration data defining the server's name, routes,
     *                                       middleware, functions, and version. This data-driven
     *                                       approach enables creating multiple RPC endpoints without
     *                                       requiring additional server class definitions.
     * @param string     $functionsPath      File system path where function classes are located for
     *                                       automatic discovery. Used when ServerData doesn't specify
     *                                       explicit function list. Retrieved from rpc.paths.functions
     *                                       configuration key.
     * @param string     $functionsNamespace Namespace prefix for discovered function classes, used to
     *                                       convert file paths to fully qualified class names during
     *                                       auto-discovery. Retrieved from rpc.namespaces.functions
     *                                       configuration key.
     */
    public function __construct(
        private readonly ServerData $server,
        #[Config('rpc.paths.functions', '')]
        private readonly string $functionsPath,
        #[Config('rpc.namespaces.functions', '')]
        private readonly string $functionsNamespace,
    ) {
        parent::__construct();
    }

    /**
     * Get the server name from configuration.
     *
     * @return string Server name defined in the configuration data
     */
    #[Override()]
    public function getName(): string
    {
        return $this->server->name;
    }

    /**
     * Get the URL path for this server from configuration.
     *
     * @return string URL path where the RPC endpoint is mounted
     */
    #[Override()]
    public function getRoutePath(): string
    {
        return $this->server->path;
    }

    /**
     * Get the Laravel route name from configuration.
     *
     * @return string Named route identifier for Laravel's routing system
     */
    #[Override()]
    public function getRouteName(): string
    {
        return $this->server->route;
    }

    /**
     * Get the API version from configuration.
     *
     * @return string Semantic version string for this server instance
     */
    #[Override()]
    public function getVersion(): string
    {
        return $this->server->version;
    }

    /**
     * Get the middleware stack from configuration.
     *
     * @return array<int, string> Array of middleware class names or aliases to apply
     */
    #[Override()]
    public function getMiddleware(): array
    {
        return $this->server->middleware;
    }

    /**
     * Get the RPC functions for this server.
     *
     * Returns functions from configuration if explicitly defined, otherwise scans
     * the configured functions directory to auto-discover function classes. The
     * discovery process filters for classes implementing FunctionInterface and
     * excludes abstract classes and test files.
     *
     * @return array<int, class-string<FunctionInterface>> Array of function class names
     */
    #[Override()]
    public function functions(): array
    {
        $functions = $this->server->functions;

        if ($functions === null) {
            $functionsPath = mb_rtrim($this->functionsPath, '/');

            if (!File::isDirectory($functionsPath)) {
                return [];
            }

            $functionsNamespace = $this->functionsNamespace;

            /** @var array<int, class-string<FunctionInterface>> */
            $discovered = collect(File::allFiles($functionsPath))
                ->map(fn ($file): string => $file->getPathname())
                ->filter(fn ($file): bool => Str::endsWith($file, ['.php']))
                ->map(fn ($file): string => str_replace($functionsPath.'/', $functionsNamespace.'\\', $file))
                ->map(fn ($file): string => str_replace('.php', '', $file))
                ->map(fn ($file): string => str_replace('/', '\\', $file))
                ->reject(fn ($file): bool => Str::contains($file, ['AbstractFunction', 'Test.php']))
                ->filter(function (string $class): bool {
                    if (!class_exists($class)) {
                        return false;
                    }

                    return in_array(FunctionInterface::class, (array) class_implements($class), true);
                })
                ->values()
                ->all();

            return $discovered;
        }

        return $functions;
    }
}

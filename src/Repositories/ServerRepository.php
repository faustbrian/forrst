<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Repositories;

use Cline\Forrst\Contracts\ServerInterface;
use Cline\Forrst\Exceptions\ServerNotFoundException;
use Closure;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

use function is_string;

/**
 * Singleton registry for Forrst server endpoint configurations.
 *
 * Maintains the collection of registered server instances, enabling lookup by
 * route path or route name during request routing. Each server defines an RPC
 * endpoint with its own route, middleware stack, function registry, and capabilities.
 *
 * Servers are registered at application boot and remain available throughout
 * the request lifecycle. Supports multiple servers per application, allowing
 * different endpoints with distinct configurations (e.g., public API vs admin API).
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/
 */
#[Singleton()]
final class ServerRepository
{
    /**
     * Registered server instances indexed by route path.
     *
     * Keys are route paths (e.g., "/api/rpc"), values are ServerInterface instances.
     *
     * @var Collection<string, ServerInterface>
     */
    private Collection $servers;

    /**
     * Initializes an empty server registry.
     */
    public function __construct()
    {
        $this->servers = new Collection();
    }

    /**
     * Returns all registered server instances.
     *
     * @return Collection<string, ServerInterface> All servers indexed by route path
     */
    public function all(): Collection
    {
        return $this->servers;
    }

    /**
     * Locates a server by its Laravel route name.
     *
     * @param string $name Route name to match (e.g., "api.rpc")
     *
     * @throws ServerNotFoundException When no server has the specified route name
     *
     * @return ServerInterface Matching server instance
     */
    public function findByName(string $name): ServerInterface
    {
        return $this->findBy(fn (ServerInterface $server): bool => $server->getRouteName() === $name);
    }

    /**
     * Locates a server by its HTTP route path.
     *
     * @param string $path Route path to match (e.g., "/api/rpc")
     *
     * @throws ServerNotFoundException When no server is registered at the path
     *
     * @return ServerInterface Matching server instance
     */
    public function findByPath(string $path): ServerInterface
    {
        return $this->findBy(fn (ServerInterface $server): bool => $server->getRoutePath() === $path);
    }

    /**
     * Registers a server endpoint in the repository.
     *
     * Accepts either a configured server instance or a class name to be resolved
     * from the Laravel container. Servers are indexed by route path for efficient
     * request routing lookup.
     *
     * @param ServerInterface|string $server Server instance or fully qualified class name
     */
    public function register(string|ServerInterface $server): void
    {
        if (is_string($server)) {
            /** @var ServerInterface $server */
            $server = App::make($server);
        }

        $this->servers[$server->getRoutePath()] = $server;
    }

    /**
     * Locates a server using a custom predicate function.
     *
     * @param Closure $closure Predicate function receiving ServerInterface and returning bool
     *
     * @throws ServerNotFoundException When no server satisfies the predicate
     *
     * @return ServerInterface First server matching the predicate
     */
    private function findBy(Closure $closure): ServerInterface
    {
        $server = $this->servers->firstWhere(
            $closure,
            fn () => throw ServerNotFoundException::create(),
        );

        if ($server instanceof ServerInterface) {
            return $server;
        }

        throw ServerNotFoundException::create();
    }
}

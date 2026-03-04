<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Mixins;

use Cline\Forrst\Contracts\ServerInterface;
use Cline\Forrst\Http\Controllers\FunctionController;
use Cline\Forrst\Repositories\ServerRepository;
use Closure;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

use function assert;
use function is_string;

/**
 * Provides Laravel Route macro for registering Forrst servers.
 *
 * Adds the `rpc()` macro to Laravel's Route facade, enabling convenient
 * registration of Forrst server endpoints with automatic route configuration,
 * middleware application, and server repository registration. Simplifies
 * server setup to a single line in routes files.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/
 *
 * @mixin Router
 *
 * @psalm-immutable
 */
final readonly class RouteMixin
{
    /**
     * Returns a closure that registers a Forrst server with Laravel routing.
     *
     * The returned closure can be used as a Route macro to register Forrst servers
     * by creating a POST route with the server's configured path, name, and middleware.
     * The server instance is also registered in the ServerRepository for runtime access.
     * Accepts either a server class name or an already-instantiated server object.
     *
     * ```php
     * // Register using class name
     * Route::rpc(MyJsonRpcServer::class);
     *
     * // Register using instance
     * Route::rpc(new MyJsonRpcServer());
     * ```
     *
     * @return Closure Closure that accepts a server class or instance and registers it with routing
     */
    public function rpc(): Closure
    {
        /**
         * Registers a Forrst server instance.
         *
         * @param class-string<ServerInterface>|ServerInterface $server Server class name or instance to register.
         *                                                              If a class name is provided, it will be
         *                                                              resolved from the container.
         */
        return function (string|ServerInterface $server): void {
            if (is_string($server)) {
                /** @var ServerInterface $server */
                $server = App::make($server);
            }

            $repository = App::make(ServerRepository::class);
            assert($repository instanceof ServerRepository);

            $repository->register($server);

            Route::post($server->getRoutePath(), FunctionController::class)
                ->name($server->getRouteName())
                ->middleware($server->getMiddleware());
        };
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Contracts\ServerInterface;
use Cline\Forrst\Facades\Server;
use Cline\Forrst\Repositories\FunctionRepository;
use Illuminate\Support\Facades\App;
use Tests\Support\Fakes\Server as FakesServer;

describe('Server Facade', function (): void {
    describe('Happy Paths', function (): void {
        test('retrieves the function repository', function (): void {
            App::instance(ServerInterface::class, new FakesServer());

            $repository = Server::getFunctionRepository();

            expect($repository)->toBeInstanceOf(FunctionRepository::class);
        });

        test('retrieves middleware', function (): void {
            App::instance(ServerInterface::class, new FakesServer());

            $retrievedMiddleware = Server::getMiddleware();

            expect($retrievedMiddleware)->toBeArray();
        });

        test('retrieves the name', function (): void {
            App::instance(ServerInterface::class, new FakesServer());

            $retrievedName = Server::getName();

            expect($retrievedName)->toBeString();
        });

        test('retrieves the route name', function (): void {
            App::instance(ServerInterface::class, new FakesServer());

            $retrievedRouteName = Server::getRouteName();

            expect($retrievedRouteName)->toBeString();
        });

        test('retrieves the route path', function (): void {
            App::instance(ServerInterface::class, new FakesServer());

            $retrievedRoutePath = Server::getRoutePath();

            expect($retrievedRoutePath)->toBeString();
        });

        test('retrieves the version', function (): void {
            App::instance(ServerInterface::class, new FakesServer());

            $retrievedVersion = Server::getVersion();

            expect($retrievedVersion)->toBeString();
        });
    });
});

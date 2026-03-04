<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Contracts\ServerInterface;
use Cline\Forrst\Exceptions\ServerNotFoundException;
use Cline\Forrst\Repositories\ServerRepository;
use Tests\Support\Fakes\Server;

describe('ServerRepository', function (): void {
    describe('Happy Paths', function (): void {
        test('registers and retrieves a server by route name', function (): void {
            $serverRepository = new ServerRepository();
            $serverRepository->register(Server::class);

            $retrievedServer = $serverRepository->findByName('rpc');

            expect($retrievedServer)->toBeInstanceOf(Server::class);
        });

        test('registers and retrieves a server by route path', function (): void {
            $serverRepository = new ServerRepository();
            $serverRepository->register(Server::class);

            $retrievedServer = $serverRepository->findByPath('/rpc');

            expect($retrievedServer)->toBeInstanceOf(Server::class);
        });

        test('retrieves all registered servers', function (): void {
            $serverMock1 = Mockery::mock(ServerInterface::class);
            $serverMock1->shouldReceive('getRoutePath')->andReturn('/rpc');

            $serverMock2 = Mockery::mock(ServerInterface::class);
            $serverMock2->shouldReceive('getRoutePath')->andReturn('/rpc-reloaded');

            $serverRepository = new ServerRepository();
            $serverRepository->register($serverMock1);
            $serverRepository->register($serverMock2);

            $servers = $serverRepository->all();

            expect($servers)->toHaveCount(2);
            expect($servers->get('/rpc'))->toBe($serverMock1);
            expect($servers->get('/rpc-reloaded'))->toBe($serverMock2);
        });
    });

    describe('Sad Paths', function (): void {
        test('throws an exception when a server is not found for a version', function (): void {
            $serverRepository = new ServerRepository();

            $serverRepository->findByPath('404');
        })->throws(ServerNotFoundException::class);
    });
});

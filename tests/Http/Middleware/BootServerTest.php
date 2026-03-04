<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Contracts\ServerInterface;
use Cline\Forrst\Http\Middleware\BootServer;
use Cline\Forrst\Repositories\ServerRepository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\Support\Fakes\Server;

use function Pest\Laravel\post;

describe('BootServer Middleware', function (): void {
    beforeEach(function (): void {
        Route::rpc(Server::class);
    });

    describe('Happy Paths', function (): void {
        test('binds ServerInterface to the container', function (): void {
            // Arrange & Act
            post(route('rpc'));

            // Assert
            expect(App::get(ServerInterface::class))->toBeInstanceOf(ServerInterface::class);
        });

        test('passes request to next middleware after binding server', function (): void {
            // Arrange
            $called = false;
            $middleware = App::make(BootServer::class);
            $request = Request::create(route('rpc'), Symfony\Component\HttpFoundation\Request::METHOD_POST);
            $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

            // Act
            $response = $middleware->handle($request, function ($req) use (&$called) {
                $called = true;

                return response()->json(['success' => true]);
            });

            // Assert
            expect($called)->toBeTrue();
            expect($response->getStatusCode())->toBe(200);
            expect(App::get(ServerInterface::class))->toBeInstanceOf(ServerInterface::class);
        });
    });

    describe('Sad Paths', function (): void {
        test('throws BadRequestHttpException when route name is null', function (): void {
            // Arrange
            $middleware = App::make(BootServer::class);
            $request = Request::create('/some-route', Symfony\Component\HttpFoundation\Request::METHOD_POST);
            // Don't set route resolver to ensure route() returns null

            // Act & Assert
            expect(fn () => $middleware->handle($request, fn () => response()->json(['success' => true])))
                ->toThrow(BadRequestHttpException::class, 'A route name is required to boot the server.');
        });

        test('throws BadRequestHttpException when route exists but has no name', function (): void {
            // Arrange
            Route::post('/unnamed-route', fn () => response()->json(['data' => 'test']));
            $middleware = App::make(BootServer::class);
            $request = Request::create('/unnamed-route', Symfony\Component\HttpFoundation\Request::METHOD_POST);
            $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

            // Act & Assert
            expect(fn () => $middleware->handle($request, fn () => response()->json(['success' => true])))
                ->toThrow(BadRequestHttpException::class, 'A route name is required to boot the server.');
        });
    });

    describe('Edge Cases', function (): void {
        test('terminate does nothing during unit tests', function (): void {
            // Arrange
            $middleware = App::make(BootServer::class);
            $request = Request::create(route('rpc'), Symfony\Component\HttpFoundation\Request::METHOD_POST);
            $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

            // Act - Handle request to bind the server
            $middleware->handle($request, fn () => response()->json(['success' => true]));

            // Verify server is bound before terminate
            expect(App::bound(ServerInterface::class))->toBeTrue();
            $serverBefore = App::get(ServerInterface::class);

            // Act - Call terminate
            $middleware->terminate();

            // Assert - Server should still be bound because we're in unit tests
            expect(App::bound(ServerInterface::class))->toBeTrue();
            expect(App::get(ServerInterface::class))->toBe($serverBefore);
        });

        test('terminate forgets ServerInterface instance when not running unit tests', function (): void {
            // Arrange
            $container = new Container();
            $serverRepository = App::make(ServerRepository::class);
            $middleware = new BootServer($container, $serverRepository);

            // Bind a server instance to the container
            $container->instance(ServerInterface::class, App::make(Server::class));
            expect($container->bound(ServerInterface::class))->toBeTrue();

            // Mock App::runningUnitTests() to return false
            App::shouldReceive('runningUnitTests')
                ->once()
                ->andReturn(false);

            // Act
            $middleware->terminate();

            // Assert - Server should be forgotten
            expect($container->bound(ServerInterface::class))->toBeFalse();
        });

        test('handles multiple sequential requests with proper cleanup simulation', function (): void {
            // Arrange
            $middleware = App::make(BootServer::class);
            $request = Request::create(route('rpc'), Symfony\Component\HttpFoundation\Request::METHOD_POST);
            $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

            // Act - First request
            $middleware->handle($request, fn () => response()->json(['success' => true]));
            $firstServer = App::get(ServerInterface::class);

            // Simulate cleanup between requests (though it won't run in unit tests)
            $middleware->terminate();

            // Act - Second request
            $middleware->handle($request, fn () => response()->json(['success' => true]));

            $secondServer = App::get(ServerInterface::class);

            // Assert - Both requests bind server successfully
            expect($firstServer)->toBeInstanceOf(ServerInterface::class);
            expect($secondServer)->toBeInstanceOf(ServerInterface::class);
        });
    });
});

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Http\Middleware\ForceJson;
use Illuminate\Http\Request;

describe('ForceJson Middleware', function (): void {
    describe('Happy Paths', function (): void {
        test('sets Content-Type and Accept headers for /rpc route', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                'HTTP_CONTENT_TYPE' => 'text/html',
                'HTTP_ACCEPT' => 'text/html',
            ]);

            $nextCalled = false;
            $next = function (Request $req) use (&$nextCalled): Request {
                $nextCalled = true;

                return $req;
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($nextCalled)->toBeTrue()
                ->and($response->headers->get('Content-Type'))->toBe('application/json')
                ->and($response->headers->get('Accept'))->toBe('application/json');
        });

        test('sets Content-Type and Accept headers for /rpc/* routes', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $paths = [
                '/rpc/v1',
                '/rpc/users',
                '/rpc/api/methods',
                '/rpc/nested/path/here',
            ];

            foreach ($paths as $path) {
                $request = Request::create($path, Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                    'HTTP_CONTENT_TYPE' => 'application/xml',
                    'HTTP_ACCEPT' => 'application/xml',
                ]);

                $next = fn (Request $req): Request => $req;

                // Act
                $response = $middleware->handle($request, $next);

                // Assert
                expect($response->headers->get('Content-Type'))->toBe('application/json', 'Failed for path: '.$path)
                    ->and($response->headers->get('Accept'))->toBe('application/json', 'Failed for path: '.$path);
            }
        });

        test('passes request to next middleware after setting headers', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST);

            $nextCalled = false;
            $receivedRequest = null;
            $next = function (Request $req) use (&$nextCalled, &$receivedRequest): Request {
                $nextCalled = true;
                $receivedRequest = $req;

                return $req;
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($nextCalled)->toBeTrue()
                ->and($receivedRequest)->toBe($request)
                ->and($response)->toBe($request);
        });

        test('overwrites existing Content-Type header for /rpc routes', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST);
            $request->headers->set('Content-Type', 'text/plain');
            $request->headers->set('Accept', 'text/plain');

            $next = fn (Request $req): Request => $req;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response->headers->get('Content-Type'))->toBe('application/json')
                ->and($response->headers->get('Accept'))->toBe('application/json');
        });

        test('handles POST requests to /rpc endpoint', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, ['method' => 'test.method']);

            $next = fn (Request $req): Request => $req;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response->headers->get('Content-Type'))->toBe('application/json')
                ->and($response->headers->get('Accept'))->toBe('application/json');
        });

        test('handles GET requests to /rpc endpoint', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_GET);

            $next = fn (Request $req): Request => $req;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response->headers->get('Content-Type'))->toBe('application/json')
                ->and($response->headers->get('Accept'))->toBe('application/json');
        });
    });

    describe('Sad Paths', function (): void {
        test('does not modify headers for non-rpc routes', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $paths = [
                '/api/users',
                '/admin',
                '/rpcfoo',  // Does not match /rpc pattern
                '/foo/rpc', // rpc not at start
                '/RPC',     // Case sensitive
            ];

            foreach ($paths as $path) {
                $request = Request::create($path, Symfony\Component\HttpFoundation\Request::METHOD_GET);
                $request->headers->set('Content-Type', 'text/html');
                $request->headers->set('Accept', 'text/html');

                $next = fn (Request $req): Request => $req;

                // Act
                $response = $middleware->handle($request, $next);

                // Assert
                expect($response->headers->get('Content-Type'))->toBe('text/html', 'Failed for path: '.$path)
                    ->and($response->headers->get('Accept'))->toBe('text/html', 'Failed for path: '.$path);
            }
        });

        test('does not set headers when route starts with /rpc but not exact match', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/rpcapi', Symfony\Component\HttpFoundation\Request::METHOD_POST); // No slash after rpc
            $request->headers->set('Content-Type', 'text/plain');
            $request->headers->set('Accept', 'text/plain');

            $next = fn (Request $req): Request => $req;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response->headers->get('Content-Type'))->toBe('text/plain')
                ->and($response->headers->get('Accept'))->toBe('text/plain');
        });

        test('passes through requests without modifying headers for non-rpc routes', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/api/endpoint', Symfony\Component\HttpFoundation\Request::METHOD_POST);
            $request->headers->set('Content-Type', 'application/xml');
            $request->headers->set('Accept', 'application/xml');

            $next = fn (Request $req): Request => $req;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert - Headers should remain unchanged for non-rpc routes
            expect($response->headers->get('Content-Type'))->toBe('application/xml')
                ->and($response->headers->get('Accept'))->toBe('application/xml');
        });
    });

    describe('Edge Cases', function (): void {
        test('handles /rpc route with trailing slash', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/rpc/', Symfony\Component\HttpFoundation\Request::METHOD_POST);
            $request->headers->set('Content-Type', 'text/plain');

            $next = fn (Request $req): Request => $req;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert - /rpc/ matches /rpc/* pattern
            expect($response->headers->get('Content-Type'))->toBe('application/json')
                ->and($response->headers->get('Accept'))->toBe('application/json');
        });

        test('handles deeply nested /rpc paths', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/rpc/v1/api/users/list/nested/deep', Symfony\Component\HttpFoundation\Request::METHOD_POST);

            $next = fn (Request $req): Request => $req;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response->headers->get('Content-Type'))->toBe('application/json')
                ->and($response->headers->get('Accept'))->toBe('application/json');
        });

        test('preserves request method and body data', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $requestData = ['jsonrpc' => '2.0', 'method' => 'test.method', 'id' => '123'];
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [], json_encode($requestData));
            $request->headers->set('Content-Type', 'application/json');

            $next = fn (Request $req): Request => $req;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response->method())->toBe('POST')
                ->and($response->json()->all())->toBe($requestData);
        });

        test('handles PUT requests to /rpc routes', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_PUT);

            $next = fn (Request $req): Request => $req;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response->headers->get('Content-Type'))->toBe('application/json')
                ->and($response->headers->get('Accept'))->toBe('application/json');
        });

        test('handles DELETE requests to /rpc routes', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_DELETE);

            $next = fn (Request $req): Request => $req;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response->headers->get('Content-Type'))->toBe('application/json')
                ->and($response->headers->get('Accept'))->toBe('application/json');
        });

        test('handles PATCH requests to /rpc routes', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_PATCH);

            $next = fn (Request $req): Request => $req;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response->headers->get('Content-Type'))->toBe('application/json')
                ->and($response->headers->get('Accept'))->toBe('application/json');
        });

        test('handles OPTIONS requests to /rpc routes', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_OPTIONS);

            $next = fn (Request $req): Request => $req;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response->headers->get('Content-Type'))->toBe('application/json')
                ->and($response->headers->get('Accept'))->toBe('application/json');
        });

        test('handles /rpc route with query parameters', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/rpc?version=2.0&debug=true', Symfony\Component\HttpFoundation\Request::METHOD_POST);

            $next = fn (Request $req): Request => $req;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response->headers->get('Content-Type'))->toBe('application/json')
                ->and($response->headers->get('Accept'))->toBe('application/json')
                ->and($response->query->get('version'))->toBe('2.0')
                ->and($response->query->get('debug'))->toBe('true');
        });

        test('handles /rpc/* route with query parameters', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/rpc/v1?api_key=test123', Symfony\Component\HttpFoundation\Request::METHOD_POST);

            $next = fn (Request $req): Request => $req;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response->headers->get('Content-Type'))->toBe('application/json')
                ->and($response->headers->get('Accept'))->toBe('application/json')
                ->and($response->query->get('api_key'))->toBe('test123');
        });

        test('sets headers when Content-Type is already application/json', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST);
            $request->headers->set('Content-Type', 'application/json');
            $request->headers->set('Accept', 'application/json');

            $next = fn (Request $req): Request => $req;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert - Should still set headers (idempotent operation)
            expect($response->headers->get('Content-Type'))->toBe('application/json')
                ->and($response->headers->get('Accept'))->toBe('application/json');
        });

        test('handles /rpc with special characters in subpath', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $paths = [
                '/rpc/user-profile',
                '/rpc/user_profile',
                '/rpc/user.profile',
                '/rpc/api-v2.1',
            ];

            foreach ($paths as $path) {
                $request = Request::create($path, Symfony\Component\HttpFoundation\Request::METHOD_POST);

                $next = fn (Request $req): Request => $req;

                // Act
                $response = $middleware->handle($request, $next);

                // Assert
                expect($response->headers->get('Content-Type'))->toBe('application/json', 'Failed for path: '.$path)
                    ->and($response->headers->get('Accept'))->toBe('application/json', 'Failed for path: '.$path);
            }
        });

        test('handles request with multiple Accept header values', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST);
            $request->headers->set('Accept', 'text/html, application/xml, application/json');

            $next = fn (Request $req): Request => $req;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert - Should overwrite with single application/json
            expect($response->headers->get('Accept'))->toBe('application/json');
        });

        test('handles request with charset in Content-Type', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST);
            $request->headers->set('Content-Type', 'application/json; charset=utf-8');

            $next = fn (Request $req): Request => $req;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert - Should overwrite with simple application/json
            expect($response->headers->get('Content-Type'))->toBe('application/json');
        });

        test('preserves other headers while setting Content-Type and Accept', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST);
            $request->headers->set('Authorization', 'Bearer token123');
            $request->headers->set('X-Custom-Header', 'custom-value');
            $request->headers->set('User-Agent', 'TestAgent/1.0');

            $next = fn (Request $req): Request => $req;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert - Other headers should remain unchanged
            expect($response->headers->get('Content-Type'))->toBe('application/json')
                ->and($response->headers->get('Accept'))->toBe('application/json')
                ->and($response->headers->get('Authorization'))->toBe('Bearer token123')
                ->and($response->headers->get('X-Custom-Header'))->toBe('custom-value')
                ->and($response->headers->get('User-Agent'))->toBe('TestAgent/1.0');
        });

        test('handles case-sensitive route matching', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $caseSensitivePaths = [
                '/RPC' => false,      // Uppercase should not match
                '/Rpc' => false,      // Mixed case should not match
                '/rpc' => true,       // Lowercase should match
                '/rpc/API' => true,   // Match /rpc/*, subpath case doesn't matter
            ];

            foreach ($caseSensitivePaths as $path => $shouldMatch) {
                $request = Request::create($path, Symfony\Component\HttpFoundation\Request::METHOD_POST);
                $request->headers->set('Content-Type', 'text/plain');
                $request->headers->set('Accept', 'text/plain');

                $next = fn (Request $req): Request => $req;

                // Act
                $response = $middleware->handle($request, $next);

                // Assert
                if ($shouldMatch) {
                    expect($response->headers->get('Content-Type'))->toBe('application/json', 'Expected match for path: '.$path);
                } else {
                    expect($response->headers->get('Content-Type'))->toBe('text/plain', 'Expected no match for path: '.$path);
                }
            }
        });

        test('handles request with no initial headers set', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST);
            // No headers set initially

            $next = fn (Request $req): Request => $req;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert - Should add headers even if none existed
            expect($response->headers->get('Content-Type'))->toBe('application/json')
                ->and($response->headers->get('Accept'))->toBe('application/json');
        });
    });

    describe('Regressions', function (): void {
        test('middleware does not break request pipeline', function (): void {
            // Arrange - Simulates multiple middleware in chain
            $middleware = new ForceJson();
            $requestData = ['test' => 'data'];
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [], json_encode($requestData));
            $request->headers->set('Content-Type', 'application/json');

            $middlewareChainExecuted = [];
            $next = function (Request $req) use (&$middlewareChainExecuted): Request {
                $middlewareChainExecuted[] = 'first';

                return $req;
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert - Next middleware should execute
            expect($middlewareChainExecuted)->toContain('first')
                ->and($response->json()->all())->toBe($requestData);
        });

        test('maintains request integrity through middleware', function (): void {
            // Arrange
            $middleware = new ForceJson();
            $requestData = [
                'jsonrpc' => '2.0',
                'method' => 'user.create',
                'params' => ['name' => 'John Doe'],
                'id' => '123',
            ];
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [], json_encode($requestData));
            $request->headers->set('Authorization', 'Bearer secret');
            $request->headers->set('Content-Type', 'application/json');

            $capturedRequest = null;
            $next = function (Request $req) use (&$capturedRequest): Request {
                $capturedRequest = $req;

                return $req;
            };

            // Act
            $middleware->handle($request, $next);

            // Assert - All request data should be preserved
            expect($capturedRequest)->not->toBeNull()
                ->and($capturedRequest->json()->all())->toBe($requestData)
                ->and($capturedRequest->headers->get('Authorization'))->toBe('Bearer secret')
                ->and($capturedRequest->headers->get('Content-Type'))->toBe('application/json')
                ->and($capturedRequest->headers->get('Accept'))->toBe('application/json');
        });
    });
});

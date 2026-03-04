<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Exceptions\FunctionNotFoundException;
use Cline\Forrst\Exceptions\InternalErrorException;
use Cline\Forrst\Exceptions\StructurallyInvalidRequestException;
use Cline\Forrst\Http\Middleware\RenderThrowable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\Support\Exceptions\SimulatedException;
use Tests\Support\Exceptions\SimulatedRuntimeException;
use Tests\Support\Exceptions\SimulatedTypeError;

describe('RenderThrowable Middleware', function (): void {
    describe('Happy Paths', function (): void {
        test('passes request through when no exception is thrown', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);

            $expectedResponse = new JsonResponse(['protocol' => ProtocolData::forrst()->toArray(), 'result' => 'success', 'id' => '123']);
            $next = fn (Request $req): JsonResponse => $expectedResponse;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response)->toBe($expectedResponse)
                ->and($response->getData(true))->toBe(['protocol' => ProtocolData::forrst()->toArray(), 'result' => 'success', 'id' => '123']);
        });

        test('renders InvalidRequestException as Forrst error', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $request->merge(['id' => '456']);

            $next = function (): void {
                throw StructurallyInvalidRequestException::create();
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getStatusCode())->toBe(400);

            $data = $response->getData(true);
            expect($data)->toHaveKey('protocol')
                ->and($data)->toHaveKey('id', '456')
                ->and($data)->toHaveKey('errors')
                ->and($data['errors'][0])->toHaveKey('code', ErrorCode::InvalidRequest->value)
                ->and($data['errors'][0])->toHaveKey('message');
        });

        test('renders FunctionNotFoundException as Forrst error', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $request->merge(['id' => 'test-123']);

            $next = function (): void {
                throw FunctionNotFoundException::create();
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getStatusCode())->toBe(404);

            $data = $response->getData(true);
            expect($data)->toHaveKey('protocol')
                ->and($data)->toHaveKey('id', 'test-123')
                ->and($data)->toHaveKey('errors')
                ->and($data['errors'][0])->toHaveKey('code', ErrorCode::FunctionNotFound->value)
                ->and($data['errors'][0])->toHaveKey('message');
        });

        test('renders InternalErrorException as Forrst error', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $request->merge(['id' => 'request-789']);

            $next = function (): void {
                throw InternalErrorException::create(
                    SimulatedException::testError(),
                );
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getStatusCode())->toBe(500);

            $data = $response->getData(true);
            expect($data)->toHaveKey('protocol')
                ->and($data)->toHaveKey('id', 'request-789')
                ->and($data)->toHaveKey('errors')
                ->and($data['errors'][0])->toHaveKey('code', ErrorCode::InternalError->value)
                ->and($data['errors'][0])->toHaveKey('message');
        });

        test('includes request id in error response when present', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $request->merge(['id' => 'custom-uuid-123']);

            $next = function (): void {
                throw StructurallyInvalidRequestException::create();
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            $data = $response->getData(true);
            expect($data['id'])->toBe('custom-uuid-123');
        });

        test('handles numeric request id', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $request->merge(['id' => 42]); // Numeric ID is invalid in Forrst (must be string)

            $next = function (): void {
                throw StructurallyInvalidRequestException::create();
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert - Forrst generates ULID when ID is not a valid string
            $data = $response->getData(true);
            expect($data['id'])->toBeString()
                ->and(mb_strlen((string) $data['id']))->toBe(26); // ULID length
        });

        test('handles string request id', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $request->merge(['id' => 'test-string-id']);

            $next = function (): void {
                throw StructurallyInvalidRequestException::create();
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            $data = $response->getData(true);
            expect($data['id'])->toBe('test-string-id');
        });
    });

    describe('Sad Paths', function (): void {
        test('re-throws exception when request does not want JSON', function (): void {
            // Arrange - Request without JSON Accept header
            $middleware = new RenderThrowable();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST);
            $request->headers->set('Accept', 'text/html');

            $next = function (): void {
                throw SimulatedRuntimeException::test();
            };

            // Act & Assert
            expect(fn (): Symfony\Component\HttpFoundation\Response => $middleware->handle($request, $next))
                ->toThrow(SimulatedRuntimeException::class, 'Test exception');
        });
    });

    describe('Edge Cases', function (): void {
        test('handles exception with null request id', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            // No id in request - Forrst generates ULID

            $next = function (): void {
                throw StructurallyInvalidRequestException::create();
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert - Forrst requires ID, generates ULID when not provided
            $data = $response->getData(true);
            expect($data)->toHaveKey('protocol')
                ->and($data)->toHaveKey('errors')
                ->and($data)->toHaveKey('id')
                ->and($data['id'])->toBeString()
                ->and(mb_strlen((string) $data['id']))->toBe(26); // ULID length
        });

        test('handles generic PHP exception', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $request->merge(['id' => 'test-id']);

            $next = function (): void {
                throw SimulatedException::somethingWentWrong();
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getStatusCode())->toBe(500);

            $data = $response->getData(true);
            expect($data)->toHaveKey('protocol')
                ->and($data)->toHaveKey('errors')
                ->and($data['errors'][0])->toHaveKey('code', ErrorCode::InternalError->value); // Internal error
        });

        test('handles TypeError exception', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $request->merge(['id' => 'type-error-test']);

            $next = function (): void {
                throw SimulatedTypeError::typeErrorOccurred();
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getStatusCode())->toBe(500);

            $data = $response->getData(true);
            expect($data)->toHaveKey('errors')
                ->and($data['errors'][0])->toHaveKey('code', ErrorCode::InternalError->value);
        });

        test('preserves response headers from mapped exception', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $request->merge(['id' => 'header-test']);

            $next = function (): void {
                throw StructurallyInvalidRequestException::create();
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->headers->get('Content-Type'))->toContain('application/json');
        });

        test('handles request with application/json Accept header', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $request->merge(['id' => 'json-accept']);

            $next = function (): void {
                throw StructurallyInvalidRequestException::create();
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response)->toBeInstanceOf(JsonResponse::class);

            $data = $response->getData(true);
            expect($data)->toHaveKey('protocol')
                ->and($data)->toHaveKey('errors');
        });

        test('handles exception thrown during response building', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $request->merge(['id' => 'response-builder-test']);

            $next = function (): never {
                throw SimulatedRuntimeException::unexpected();
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getStatusCode())->toBe(500);
        });

        test('does not filter protocol field from response', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);

            $next = function (): void {
                throw StructurallyInvalidRequestException::create();
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            $data = $response->getData(true);
            expect($data)->toHaveKey('protocol')
                ->and($data['protocol'])->toBe(ProtocolData::forrst()->toArray()); // Always present
        });

        test('handles POST request with JSON body', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $requestBody = json_encode([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'body-test',
                'call' => [
                    'function' => 'test.method',
                ],
            ]);
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ], $requestBody);

            $next = function (): void {
                throw StructurallyInvalidRequestException::create();
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response)->toBeInstanceOf(JsonResponse::class);

            $data = $response->getData(true);
            expect($data)->toHaveKey('protocol')
                ->and($data)->toHaveKey('errors');
        });

        test('handles zero as request id', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $request->merge(['id' => 0]); // Zero is not a valid string ID

            $next = function (): void {
                throw StructurallyInvalidRequestException::create();
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert - Forrst generates ULID when ID is not a valid string
            $data = $response->getData(true);
            expect($data)->toHaveKey('id')
                ->and($data['id'])->toBeString()
                ->and(mb_strlen((string) $data['id']))->toBe(26); // ULID length
        });

        test('handles empty string as request id', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $request->merge(['id' => '']); // Empty string is not a valid ID

            $next = function (): void {
                throw StructurallyInvalidRequestException::create();
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert - Forrst generates ULID when ID is empty string
            $data = $response->getData(true);
            expect($data)->toHaveKey('id')
                ->and($data['id'])->toBeString()
                ->and(mb_strlen((string) $data['id']))->toBe(26); // ULID length
        });
    });

    describe('Regressions', function (): void {
        test('does not break request pipeline on success', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);

            $nextCalled = false;
            $next = function (Request $req) use (&$nextCalled): Response {
                $nextCalled = true;

                return new Response('success');
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($nextCalled)->toBeTrue()
                ->and($response->getContent())->toBe('success');
        });

        test('maintains request object integrity', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $requestData = [
                'jsonrpc' => '2.0',
                'method' => 'test.method',
                'params' => ['key' => 'value'],
                'id' => 'integrity-test',
            ];
            $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, $requestData, [], [], [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer token123',
            ]);

            $capturedRequest = null;
            $next = function (Request $req) use (&$capturedRequest): Response {
                $capturedRequest = $req;

                return new Response('ok');
            };

            // Act
            $middleware->handle($request, $next);

            // Assert
            expect($capturedRequest)->not->toBeNull()
                ->and($capturedRequest->input('jsonrpc'))->toBe('2.0')
                ->and($capturedRequest->input('method'))->toBe('test.method')
                ->and($capturedRequest->input('id'))->toBe('integrity-test')
                ->and($capturedRequest->header('Authorization'))->toBe('Bearer token123');
        });

        test('consistent error format across different exception types', function (): void {
            // Arrange
            $middleware = new RenderThrowable();
            $exceptions = [
                StructurallyInvalidRequestException::create(),
                FunctionNotFoundException::create(),
                InternalErrorException::create(
                    SimulatedException::testError(),
                ),
            ];

            foreach ($exceptions as $exception) {
                $request = Request::create('/rpc', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
                    'HTTP_ACCEPT' => 'application/json',
                ]);
                $request->merge(['id' => 'format-test']);

                $next = function () use ($exception): void {
                    throw $exception;
                };

                // Act
                $response = $middleware->handle($request, $next);

                // Assert
                $data = $response->getData(true);
                expect($data)->toHaveKey('protocol')
                    ->and($data)->toHaveKey('errors')
                    ->and($data['errors'][0])->toHaveKey('code')
                    ->and($data['errors'][0])->toHaveKey('message')
                    ->and($data['errors'][0]['code'])->toBeString();
            }
        });
    });
});

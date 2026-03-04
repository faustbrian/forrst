<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Contracts\ServerInterface;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Requests\RequestHandler;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Tests\Support\Fakes\Server;

describe('RequestHandler Complete Coverage', function (): void {
    beforeEach(function (): void {
        Route::rpc(Server::class);
        App::bind(ServerInterface::class, Server::class);
    });

    describe('Happy Paths', function (): void {
        test('can call a function from a Forrst format array', function (): void {
            $result = RequestHandler::createFromArray([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'req-1',
                'call' => [
                    'function' => 'urn:app:forrst:fn:subtract-with-binding',
                    'arguments' => ['subtrahend' => 23, 'minuend' => 42],
                ],
            ]);

            // Note: AbstractData removes null values, so 'error' is not in the array
            expect($result->statusCode)->toBe(200)
                ->and($result->data->protocol)->toBeInstanceOf(ProtocolData::class)
                ->and($result->data->id)->toBe('req-1')
                ->and($result->data->result)->toBe(19)
                ->and($result->data->getFirstError())->toBeNull();
        });

        test('can call a function from a Forrst format string', function (): void {
            $result = RequestHandler::createFromString(
                json_encode([
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => 'req-2',
                    'call' => [
                        'function' => 'urn:app:forrst:fn:subtract-with-binding',
                        'arguments' => ['subtrahend' => 23, 'minuend' => 42],
                    ],
                ]),
            );

            // Note: AbstractData removes null values, so 'error' is not in the array
            expect($result->statusCode)->toBe(200)
                ->and($result->data->protocol)->toBeInstanceOf(ProtocolData::class)
                ->and($result->data->id)->toBe('req-2')
                ->and($result->data->result)->toBe(19)
                ->and($result->data->getFirstError())->toBeNull();
        });

        test('rejects legacy JSON-RPC format (no longer supported)', function (): void {
            // Legacy JSON-RPC format is no longer supported in Forrst
            $result = RequestHandler::createFromArray([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'urn:app:forrst:fn:subtract-with-binding',
                'params' => [
                    'data' => ['subtrahend' => 23, 'minuend' => 42],
                ],
            ]);

            // Should return InvalidRequest error since JSON-RPC format is not supported
            expect($result->statusCode)->toBe(400)
                ->and($result->data->getFirstError()->code)->toBe(ErrorCode::InvalidRequest->value);
        });

        test('can call a function without arguments', function (): void {
            $result = RequestHandler::createFromArray([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'req-3',
                'call' => [
                    'function' => 'urn:app:forrst:fn:get-data',
                ],
            ]);

            expect($result->statusCode)->toBe(200)
                ->and($result->data->protocol)->toBeInstanceOf(ProtocolData::class)
                ->and($result->data->id)->toBe('req-3')
                ->and($result->data->getFirstError())->toBeNull();
        });
    });

    describe('Method Exception Handling', function (): void {
        test('handles authentication exception from method', function (): void {
            // Arrange
            $request = [
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'req-auth-1',
                'call' => [
                    'function' => 'urn:app:forrst:fn:requires-authentication',
                    'arguments' => [],
                ],
            ];

            // Act
            $result = RequestHandler::createFromArray($request);

            // Assert - CallFunction catches exception and returns error response with 401
            // The exception is mapped to UNAUTHORIZED error code in the response body
            expect($result->statusCode)->toBe(401)
                ->and($result->data->protocol)->toBeInstanceOf(ProtocolData::class)
                ->and($result->data->id)->toBe('req-auth-1')
                ->and($result->data->getFirstError()->code)->toBe(ErrorCode::Unauthorized->value)
                ->and($result->data->getFirstError()->message)->toBe('Unauthorized');
        });

        test('handles authorization exception from method', function (): void {
            // Arrange
            $request = [
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'req-auth-2',
                'call' => [
                    'function' => 'urn:app:forrst:fn:requires-authorization',
                    'arguments' => [],
                ],
            ];

            // Act
            $result = RequestHandler::createFromArray($request);

            // Assert - CallFunction catches exception and returns error response with 403
            // The exception is mapped to FORBIDDEN error code in the response body
            expect($result->statusCode)->toBe(403)
                ->and($result->data->protocol)->toBeInstanceOf(ProtocolData::class)
                ->and($result->data->id)->toBe('req-auth-2')
                ->and($result->data->getFirstError()->code)->toBe(ErrorCode::Forbidden->value)
                ->and($result->data->getFirstError()->message)->toBe('Forbidden');
        });

        test('handles function not found error', function (): void {
            // Arrange
            $request = [
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'req-notfound',
                'call' => [
                    'function' => 'urn:app:forrst:fn:nonexistent-function',
                ],
            ];

            // Act
            $result = RequestHandler::createFromArray($request);

            // Assert
            expect($result->statusCode)->toBe(404)
                ->and($result->data->getFirstError()->code)->toBe(ErrorCode::FunctionNotFound->value);
        });
    });

    describe('Batch Requests', function (): void {
        test('batch requests return error (not supported in Forrst)', function (): void {
            // Arrange - batch is an array of arrays
            $batchRequest = [
                [
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => 'req-1',
                    'call' => [
                        'function' => 'urn:app:forrst:fn:sum',
                        'arguments' => ['data' => [1, 2, 3]],
                    ],
                ],
                [
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => 'req-2',
                    'call' => [
                        'function' => 'urn:app:forrst:fn:subtract',
                    ],
                ],
            ];

            // Act
            $result = RequestHandler::createFromArray($batchRequest);

            // Assert - batch not supported, returns error
            expect($result->statusCode)->toBe(400)
                ->and($result->data->getFirstError()->code)->toBe(ErrorCode::InvalidRequest->value);
        });
    });

    describe('Request ID Requirement', function (): void {
        test('requests without id field return error (id is required in Forrst)', function (): void {
            // Arrange - request missing required 'id' field
            $request = [
                'protocol' => ProtocolData::forrst()->toArray(),
                'call' => [
                    'function' => 'urn:app:forrst:fn:notify-hello',
                    'arguments' => ['world'],
                ],
            ];

            // Act
            $result = RequestHandler::createFromArray($request);

            // Assert - id is required, returns InvalidRequest error
            expect($result->statusCode)->toBe(400)
                ->and($result->data->getFirstError()->code)->toBe(ErrorCode::InvalidRequest->value);
        });

        test('legacy JSON-RPC format without id returns error', function (): void {
            // Arrange - JSON-RPC format (no longer supported)
            $request = [
                'jsonrpc' => '2.0',
                'method' => 'urn:app:forrst:fn:notify-hello',
                'params' => ['world'],
            ];

            // Act
            $result = RequestHandler::createFromArray($request);

            // Assert - JSON-RPC format not supported, returns error
            expect($result->statusCode)->toBe(400)
                ->and($result->data->getFirstError()->code)->toBe(ErrorCode::InvalidRequest->value);
        });
    });

    describe('Parse Errors', function (): void {
        test('handles invalid JSON string', function (): void {
            // Act
            $result = RequestHandler::createFromString('not valid json');

            // Assert
            expect($result->statusCode)->toBe(400)
                ->and($result->data->getFirstError()->code)->toBe(ErrorCode::ParseError->value);
        });

        test('handles empty array', function (): void {
            // Act
            $result = RequestHandler::createFromArray([]);

            // Assert
            expect($result->statusCode)->toBe(400)
                ->and($result->data->getFirstError()->code)->toBe(ErrorCode::InvalidRequest->value);
        });
    });

    describe('Validation Errors', function (): void {
        test('handles missing protocol field', function (): void {
            // Arrange
            $request = [
                'id' => 'req-1',
                'call' => [
                    'function' => 'urn:app:forrst:fn:sum',
                ],
            ];

            // Act
            $result = RequestHandler::createFromArray($request);

            // Assert
            expect($result->statusCode)->toBe(400)
                ->and($result->data->getFirstError()->code)->toBe(ErrorCode::InvalidRequest->value);
        });

        test('handles missing call field', function (): void {
            // Arrange
            $request = [
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'req-1',
            ];

            // Act
            $result = RequestHandler::createFromArray($request);

            // Assert
            expect($result->statusCode)->toBe(400)
                ->and($result->data->getFirstError()->code)->toBe(ErrorCode::InvalidRequest->value);
        });

        test('handles missing function in call', function (): void {
            // Arrange
            $request = [
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'req-1',
                'call' => [
                    'arguments' => ['data' => [1, 2, 3]],
                ],
            ];

            // Act
            $result = RequestHandler::createFromArray($request);

            // Assert
            expect($result->statusCode)->toBe(400)
                ->and($result->data->getFirstError()->code)->toBe(ErrorCode::InvalidRequest->value);
        });
    });
});

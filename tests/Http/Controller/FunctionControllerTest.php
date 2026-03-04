<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\Fakes\Server;
use Tests\Support\FunctionCaller;

use function Pest\Laravel\call;

// These tests are based on the examples from https://www.jsonrpc.org/specification

describe('FunctionController', function (): void {
    beforeEach(function (): void {
        Route::rpc(Server::class);
    });

    describe('Happy Paths', function (): void {
        test('returns JSON response with HTTP 200 for successful function call', function (): void {
            // Arrange
            $request = json_encode([
                'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
                'id' => 'req_success',
                'call' => [
                    'function' => 'urn:app:forrst:fn:sum',
                    'arguments' => [1, 2, 3],
                ],
            ]);

            // Act
            $response = call('POST', URL::to('/rpc'), [], [], [], [], $request);

            // Assert
            $response->assertStatus(200);
            $response->assertHeader('Content-Type', 'application/json');
            $response->assertJsonPath('result', 6);
        });

        test('returns JSON response with Spatie Data object converted to array', function (): void {
            FunctionCaller::call('rpc-call-spatie-data-response');
        });

        test('processes forrst.describe discovery request successfully', function (): void {
            FunctionCaller::call('forrst-describe');
        });

        test('handles function call with positional parameters', function (): void {
            FunctionCaller::call('rpc-call-with-positional-parameters-1');
            FunctionCaller::call('rpc-call-with-positional-parameters-2');
        });

        test('handles function call with named parameters', function (): void {
            FunctionCaller::call('rpc-call-with-named-parameters-1');
            FunctionCaller::call('rpc-call-with-named-parameters-2');
        });

        test('processes notification without returning response', function (): void {
            FunctionCaller::call('rpc-call-with-a-notification');
        });

        test('handles batch requests with multiple function calls', function (): void {
            FunctionCaller::call('rpc-call-batch');
        });

        test('processes batch with all notifications', function (): void {
            FunctionCaller::call('rpc-call-batch-all-notifications');
        });

        test('returns Collection response properly serialized', function (): void {
            FunctionCaller::call('rpc-call-collection-response');
        });

        test('returns StreamedResponse for streamable function with stream extension enabled', function (): void {
            // Arrange
            $request = json_encode([
                'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
                'id' => 'req_stream_enabled',
                'call' => [
                    'function' => 'urn:app:forrst:fn:streaming-sum',
                    'arguments' => [1, 2, 3, 4, 5],
                ],
                'extensions' => [
                    [
                        'urn' => 'urn:cline:forrst:ext:stream',
                        'options' => ['accept' => true],
                    ],
                ],
            ]);

            // Act
            $response = call('POST', URL::to('/rpc'), [], [], [], [], $request);

            // Assert
            $response->assertStatus(200);

            expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);
            $response->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');
            $response->assertHeader('Cache-Control', 'no-cache, private');
            $response->assertHeader('Connection', 'keep-alive');
        });

        test('sends initial connected event when streaming starts', function (): void {
            // Arrange
            $request = json_encode([
                'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
                'id' => 'req_stream_init',
                'call' => [
                    'function' => 'urn:app:forrst:fn:streaming-sum',
                    'arguments' => [10, 20],
                ],
                'extensions' => [
                    [
                        'urn' => 'urn:cline:forrst:ext:stream',
                        'options' => ['accept' => true],
                    ],
                ],
            ]);

            // Act
            $response = call('POST', URL::to('/rpc'), [], [], [], [], $request);

            // Assert - verify streaming response is created
            expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);
            $response->assertStatus(200);
        });

        test('returns JSON for streamable function when stream extension is not requested', function (): void {
            // Arrange
            $request = json_encode([
                'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
                'id' => 'req_no_stream',
                'call' => [
                    'function' => 'urn:app:forrst:fn:streaming-sum',
                    'arguments' => [5, 10, 15],
                ],
            ]);

            // Act
            $response = call('POST', URL::to('/rpc'), [], [], [], [], $request);

            // Assert
            $response->assertStatus(200);
            $response->assertHeader('Content-Type', 'application/json');
            $response->assertJsonPath('result.sum', 30);
        });

        test('returns JSON when stream extension accept is false', function (): void {
            // Arrange
            $request = json_encode([
                'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
                'id' => 'req_stream_disabled',
                'call' => [
                    'function' => 'urn:app:forrst:fn:streaming-sum',
                    'arguments' => [7, 8, 9],
                ],
                'extensions' => [
                    [
                        'urn' => 'urn:cline:forrst:ext:stream',
                        'options' => ['accept' => false],
                    ],
                ],
            ]);

            // Act
            $response = call('POST', URL::to('/rpc'), [], [], [], [], $request);

            // Assert
            $response->assertStatus(200);
            $response->assertHeader('Content-Type', 'application/json');
            $response->assertJsonPath('result.sum', 24);
        });
    });

    describe('Sad Paths', function (): void {
        test('returns HTTP 404 for non-existent function', function (): void {
            // Arrange & Act & Assert
            // FUNCTION_NOT_FOUND error maps to HTTP 404
            FunctionCaller::call('rpc-call-of-non-existent-method', 404);
        });

        test('returns HTTP 400 for invalid JSON', function (): void {
            // PARSE_ERROR error maps to HTTP 400
            FunctionCaller::call('rpc-call-with-invalid-json', 400);
        });

        test('returns HTTP 400 for malformed request object', function (): void {
            // INVALID_REQUEST error maps to HTTP 400
            FunctionCaller::call('rpc-call-with-invalid-request-object', 400);
        });

        test('returns HTTP 400 for invalid batch JSON', function (): void {
            // PARSE_ERROR error maps to HTTP 400
            FunctionCaller::call('rpc-call-batch-invalid-json', 400);
        });

        test('returns HTTP 400 for empty array batch', function (): void {
            // INVALID_REQUEST error maps to HTTP 400
            FunctionCaller::call('rpc-call-with-an-empty-array', 400);
        });

        test('returns HTTP 400 for invalid batch items', function (): void {
            // INVALID_REQUEST error maps to HTTP 400
            FunctionCaller::call('rpc-call-with-an-invalid-batch-but-not-empty', 400);
        });

        test('returns HTTP 400 for completely invalid batch', function (): void {
            // INVALID_REQUEST error maps to HTTP 400
            FunctionCaller::call('rpc-call-with-invalid-batch', 400);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles streaming with empty arguments array', function (): void {
            // Arrange
            $request = json_encode([
                'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
                'id' => 'req_stream_empty',
                'call' => [
                    'function' => 'urn:app:forrst:fn:streaming-sum',
                    'arguments' => [],
                ],
                'extensions' => [
                    [
                        'urn' => 'urn:cline:forrst:ext:stream',
                        'options' => ['accept' => true],
                    ],
                ],
            ]);

            // Act
            $response = call('POST', URL::to('/rpc'), [], [], [], [], $request);

            // Assert
            $response->assertStatus(200);

            expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);
        });

        test('returns error for non-streamable function with stream extension requested', function (): void {
            // Arrange - Sum is not a StreamableFunction
            $request = json_encode([
                'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
                'id' => 'req_not_streamable',
                'call' => [
                    'function' => 'urn:app:forrst:fn:sum',
                    'arguments' => [1, 2, 3],
                ],
                'extensions' => [
                    [
                        'urn' => 'urn:cline:forrst:ext:stream',
                        'options' => ['accept' => true],
                    ],
                ],
            ]);

            // Act
            $response = call('POST', URL::to('/rpc'), [], [], [], [], $request);

            // Assert - returns JSON with extension error
            // EXTENSION_NOT_APPLICABLE error maps to HTTP 400
            $response->assertStatus(400);
            $response->assertHeader('Content-Type', 'application/json');
            $response->assertJsonPath('errors.0.code', 'EXTENSION_NOT_APPLICABLE');
            $response->assertJsonPath('errors.0.details.extension', 'urn:cline:forrst:ext:stream');
        });

        test('sets correct SSE headers for streaming response', function (): void {
            // Arrange
            $request = json_encode([
                'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
                'id' => 'req_sse_headers',
                'call' => [
                    'function' => 'urn:app:forrst:fn:streaming-sum',
                    'arguments' => [100],
                ],
                'extensions' => [
                    [
                        'urn' => 'urn:cline:forrst:ext:stream',
                        'options' => ['accept' => true],
                    ],
                ],
            ]);

            // Act
            $response = call('POST', URL::to('/rpc'), [], [], [], [], $request);

            // Assert
            expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);
            $response->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');
            $response->assertHeader('Cache-Control', 'no-cache, private');
            $response->assertHeader('Connection', 'keep-alive');
            $response->assertHeader('X-Accel-Buffering', 'no');
        });

        test('handles single element in streaming arguments', function (): void {
            // Arrange
            $request = json_encode([
                'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
                'id' => 'req_single_stream',
                'call' => [
                    'function' => 'urn:app:forrst:fn:streaming-sum',
                    'arguments' => [42],
                ],
                'extensions' => [
                    [
                        'urn' => 'urn:cline:forrst:ext:stream',
                        'options' => ['accept' => true],
                    ],
                ],
            ]);

            // Act
            $response = call('POST', URL::to('/rpc'), [], [], [], [], $request);

            // Assert
            expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);
            $response->assertStatus(200);
        });
    });
});

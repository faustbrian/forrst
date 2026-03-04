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

use function Pest\Laravel\call;

describe('Streaming Controller', function (): void {
    beforeEach(function (): void {
        Route::rpc(Server::class);
    });

    describe('Happy Paths', function (): void {
        test('returns SSE stream for streamable function with accept true', function (): void {
            // Arrange
            $request = json_encode([
                'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
                'id' => 'req_stream_test',
                'call' => [
                    'function' => 'urn:app:forrst:fn:streaming-sum',
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

            // Assert - verify response type and headers
            $response->assertStatus(200);
            $response->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');
            $response->assertHeader('Cache-Control', 'no-cache, private');

            // Verify it's a StreamedResponse
            expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);
        });

        test('returns JSON for streamable function when accept is false', function (): void {
            // Arrange
            $request = json_encode([
                'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
                'id' => 'req_no_stream',
                'call' => [
                    'function' => 'urn:app:forrst:fn:streaming-sum',
                    'arguments' => [1, 2, 3],
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
            $response->assertJsonPath('result.sum', 6);
        });

        test('returns JSON for streamable function without stream extension', function (): void {
            // Arrange
            $request = json_encode([
                'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
                'id' => 'req_no_ext',
                'call' => [
                    'function' => 'urn:app:forrst:fn:streaming-sum',
                    'arguments' => [5, 10],
                ],
            ]);

            // Act
            $response = call('POST', URL::to('/rpc'), [], [], [], [], $request);

            // Assert
            $response->assertStatus(200);
            $response->assertHeader('Content-Type', 'application/json');
            $response->assertJsonPath('result.sum', 15);
        });
    });

    describe('Edge Cases', function (): void {
        test('returns error for non-streamable function with stream extension', function (): void {
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

            // Assert
            $response->assertStatus(200);
            $response->assertHeader('Content-Type', 'application/json');
            $response->assertJsonPath('errors.0.code', 'EXTENSION_NOT_APPLICABLE');
            $response->assertJsonPath('errors.0.details.extension', 'urn:cline:forrst:ext:stream');
        });

        test('SSE stream returns StreamedResponse with correct headers', function (): void {
            // Arrange
            $request = json_encode([
                'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
                'id' => 'req_termination',
                'call' => [
                    'function' => 'urn:app:forrst:fn:streaming-sum',
                    'arguments' => [1],
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

            // Assert - response is StreamedResponse with SSE headers
            expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);
            $response->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');
        });

        test('handles empty arguments in streaming', function (): void {
            // Arrange
            $request = json_encode([
                'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
                'id' => 'req_empty',
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

            // Assert - streams successfully even with empty arguments
            $response->assertStatus(200);

            expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);
            $response->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');
        });
    });
});

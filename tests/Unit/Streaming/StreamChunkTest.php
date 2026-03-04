<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Streaming\StreamChunk;

describe('StreamChunk', function (): void {
    describe('Happy Paths', function (): void {
        test('creates data chunk with payload', function (): void {
            // Arrange & Act
            $chunk = StreamChunk::data(['content' => 'hello']);

            // Assert
            expect($chunk->data)->toBe(['content' => 'hello']);
            expect($chunk->event)->toBe(StreamChunk::EVENT_DATA);
            expect($chunk->final)->toBeFalse();
            expect($chunk->id)->toBeNull();
        });

        test('creates progress chunk with percentage', function (): void {
            // Arrange & Act
            $chunk = StreamChunk::progress(50, 100, 'Processing...');

            // Assert
            expect($chunk->data)->toBe([
                'current' => 50,
                'total' => 100,
                'percent' => 50,
                'message' => 'Processing...',
            ]);
            expect($chunk->event)->toBe(StreamChunk::EVENT_PROGRESS);
            expect($chunk->final)->toBeFalse();
        });

        test('creates error chunk marked as final', function (): void {
            // Arrange & Act
            $chunk = StreamChunk::error('ERROR_CODE', 'Something went wrong', ['key' => 'value']);

            // Assert
            expect($chunk->data)->toBe([
                'code' => 'ERROR_CODE',
                'message' => 'Something went wrong',
                'details' => ['key' => 'value'],
            ]);
            expect($chunk->event)->toBe(StreamChunk::EVENT_ERROR);
            expect($chunk->final)->toBeTrue();
        });

        test('creates done chunk marked as final', function (): void {
            // Arrange & Act
            $chunk = StreamChunk::done(['result' => 'complete']);

            // Assert
            expect($chunk->data)->toBe(['result' => 'complete']);
            expect($chunk->event)->toBe(StreamChunk::EVENT_DONE);
            expect($chunk->final)->toBeTrue();
        });

        test('formats as SSE with event and data', function (): void {
            // Arrange
            $chunk = StreamChunk::data(['message' => 'hello']);

            // Act
            $sse = $chunk->toSse();

            // Assert
            expect($sse)->toContain('event: data');
            expect($sse)->toContain('data: {"message":"hello"}');
            expect($sse)->toEndWith("\n\n");
        });

        test('formats as SSE with optional id', function (): void {
            // Arrange
            $chunk = new StreamChunk(
                data: ['test' => true],
                event: StreamChunk::EVENT_DATA,
                id: 'event_123',
            );

            // Act
            $sse = $chunk->toSse();

            // Assert
            expect($sse)->toContain('id: event_123');
            expect($sse)->toContain('event: data');
            expect($sse)->toContain('data: {"test":true}');
        });

        test('progress calculates percentage correctly', function (): void {
            // Arrange & Act
            $chunk = StreamChunk::progress(33, 100);

            // Assert
            expect($chunk->data['percent'])->toBe(33);
        });

        test('progress rounds percentage', function (): void {
            // Arrange & Act
            $chunk = StreamChunk::progress(1, 3);

            // Assert
            expect($chunk->data['percent'])->toBe(33);
        });
    });

    describe('Edge Cases', function (): void {
        test('progress handles zero total gracefully', function (): void {
            // Arrange & Act
            $chunk = StreamChunk::progress(0, 0);

            // Assert
            expect($chunk->data['percent'])->toBe(0);
        });

        test('progress works without message', function (): void {
            // Arrange & Act
            $chunk = StreamChunk::progress(50, 100);

            // Assert
            expect($chunk->data['message'])->toBeNull();
        });

        test('error works without details', function (): void {
            // Arrange & Act
            $chunk = StreamChunk::error('CODE', 'message');

            // Assert
            expect($chunk->data['details'])->toBeNull();
        });

        test('done works without result', function (): void {
            // Arrange & Act
            $chunk = StreamChunk::done();

            // Assert
            expect($chunk->data)->toBeNull();
            expect($chunk->final)->toBeTrue();
        });

        test('data handles scalar values', function (): void {
            // Arrange & Act
            $chunk = StreamChunk::data('simple string');

            // Assert
            expect($chunk->data)->toBe('simple string');
        });

        test('data handles null values', function (): void {
            // Arrange & Act
            $chunk = StreamChunk::data(null);

            // Assert
            expect($chunk->data)->toBeNull();
        });

        test('SSE encodes special characters in JSON', function (): void {
            // Arrange - use actual newline character
            $chunk = StreamChunk::data(['content' => "line1\nline2"]);

            // Act
            $sse = $chunk->toSse();

            // Assert - JSON encodes newlines as \n
            expect($sse)->toContain('data: {"content":"line1\nline2"}');
        });
    });

    describe('Constants', function (): void {
        test('EVENT_DATA constant is data', function (): void {
            expect(StreamChunk::EVENT_DATA)->toBe('data');
        });

        test('EVENT_PROGRESS constant is progress', function (): void {
            expect(StreamChunk::EVENT_PROGRESS)->toBe('progress');
        });

        test('EVENT_ERROR constant is error', function (): void {
            expect(StreamChunk::EVENT_ERROR)->toBe('error');
        });

        test('EVENT_DONE constant is done', function (): void {
            expect(StreamChunk::EVENT_DONE)->toBe('done');
        });
    });
});

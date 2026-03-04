<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\CallData;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Streaming\StreamChunk;
use Tests\Support\Fakes\Functions\StreamingSum;

describe('StreamingSum', function (): void {
    describe('Streaming Output', function (): void {
        test('streams correct sequence of events for multiple numbers', function (): void {
            // Arrange
            $function = new StreamingSum();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'test_stream',
                call: new CallData(
                    function: 'urn:app:forrst:fn:streaming-sum',
                    arguments: [1, 2, 3],
                ),
            );
            $function->setRequest($request);

            // Act
            $chunks = iterator_to_array($function->stream());

            // Assert - verify chunk sequence
            expect($chunks)->toHaveCount(7); // 3 progress + 3 data + 1 done

            // First number: progress + data
            expect($chunks[0]->event)->toBe(StreamChunk::EVENT_PROGRESS);
            expect($chunks[0]->data['current'])->toBe(1);
            expect($chunks[0]->data['total'])->toBe(3);
            expect($chunks[0]->data['message'])->toBe('Processing number 1');

            expect($chunks[1]->event)->toBe(StreamChunk::EVENT_DATA);
            expect($chunks[1]->data['partial_sum'])->toBe(1);

            // Second number: progress + data
            expect($chunks[2]->event)->toBe(StreamChunk::EVENT_PROGRESS);
            expect($chunks[2]->data['current'])->toBe(2);

            expect($chunks[3]->event)->toBe(StreamChunk::EVENT_DATA);
            expect($chunks[3]->data['partial_sum'])->toBe(3); // 1 + 2

            // Third number: progress + data
            expect($chunks[4]->event)->toBe(StreamChunk::EVENT_PROGRESS);
            expect($chunks[4]->data['current'])->toBe(3);

            expect($chunks[5]->event)->toBe(StreamChunk::EVENT_DATA);
            expect($chunks[5]->data['partial_sum'])->toBe(6); // 1 + 2 + 3

            // Final done event
            expect($chunks[6]->event)->toBe(StreamChunk::EVENT_DONE);
            expect($chunks[6]->data['sum'])->toBe(6);
            expect($chunks[6]->final)->toBeTrue();
        });

        test('streams correct SSE format', function (): void {
            // Arrange
            $function = new StreamingSum();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'test_sse',
                call: new CallData(
                    function: 'urn:app:forrst:fn:streaming-sum',
                    arguments: [5],
                ),
            );
            $function->setRequest($request);

            // Act - collect SSE output
            $sseOutput = '';

            foreach ($function->stream() as $chunk) {
                $sseOutput .= $chunk->toSse();
            }

            // Assert - verify SSE format
            expect($sseOutput)->toContain('event: progress');
            expect($sseOutput)->toContain('event: data');
            expect($sseOutput)->toContain('event: done');
            expect($sseOutput)->toContain('"partial_sum":5');
            expect($sseOutput)->toContain('"sum":5');
            expect($sseOutput)->toContain('data: ');
            expect($sseOutput)->toMatch('/event: \w+\ndata: \{.*\}\n\n/');
        });

        test('streams empty result for no arguments', function (): void {
            // Arrange
            $function = new StreamingSum();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'test_empty',
                call: new CallData(
                    function: 'urn:app:forrst:fn:streaming-sum',
                    arguments: [],
                ),
            );
            $function->setRequest($request);

            // Act
            $chunks = iterator_to_array($function->stream());

            // Assert - only done event with sum of 0
            expect($chunks)->toHaveCount(1);
            expect($chunks[0]->event)->toBe(StreamChunk::EVENT_DONE);
            expect($chunks[0]->data['sum'])->toBe(0);
        });

        test('non-streaming handle returns correct result', function (): void {
            // Arrange
            $function = new StreamingSum();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'test_handle',
                call: new CallData(
                    function: 'urn:app:forrst:fn:streaming-sum',
                    arguments: [10, 20, 30],
                ),
            );

            // Act
            $result = $function->handle($request);

            // Assert
            expect($result)->toBe(['sum' => 60]);
        });
    });
});

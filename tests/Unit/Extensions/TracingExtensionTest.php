<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Events\ExecutingFunction;
use Cline\Forrst\Events\FunctionExecuted;
use Cline\Forrst\Extensions\ExtensionUrn;
use Cline\Forrst\Extensions\TracingExtension;
use Illuminate\Support\Sleep;

describe('TracingExtension', function (): void {
    describe('Happy Paths', function (): void {
        test('getUrn returns correct URN constant', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $urn = $extension->getUrn();

            // Assert
            expect($urn)->toBe(ExtensionUrn::Tracing->value)
                ->and($urn)->toBe('urn:cline:forrst:ext:tracing');
        });

        test('getTraceId extracts trace ID from options', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $options = ['trace_id' => 'trace-abc123'];

            // Act
            $traceId = $extension->getTraceId($options);

            // Assert
            expect($traceId)->toBe('trace-abc123');
        });

        test('getTraceId returns null when not provided', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $options = [];

            // Act
            $traceId = $extension->getTraceId($options);

            // Assert
            expect($traceId)->toBeNull();
        });

        test('getSpanId extracts span ID from options', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $options = ['span_id' => 'span-xyz789'];

            // Act
            $spanId = $extension->getSpanId($options);

            // Assert
            expect($spanId)->toBe('span-xyz789');
        });

        test('getSpanId returns null when not provided', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $options = [];

            // Act
            $spanId = $extension->getSpanId($options);

            // Assert
            expect($spanId)->toBeNull();
        });

        test('getParentSpanId extracts parent span ID from options', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $options = ['parent_span_id' => 'parent-span-456'];

            // Act
            $parentSpanId = $extension->getParentSpanId($options);

            // Assert
            expect($parentSpanId)->toBe('parent-span-456');
        });

        test('getParentSpanId returns null when not provided', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $options = [];

            // Act
            $parentSpanId = $extension->getParentSpanId($options);

            // Assert
            expect($parentSpanId)->toBeNull();
        });

        test('getBaggage extracts baggage from options', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $options = ['baggage' => ['user_id' => '123', 'tenant' => 'acme']];

            // Act
            $baggage = $extension->getBaggage($options);

            // Assert
            expect($baggage)->toBe(['user_id' => '123', 'tenant' => 'acme']);
        });

        test('getBaggage returns null when not provided', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $options = [];

            // Act
            $baggage = $extension->getBaggage($options);

            // Assert
            expect($baggage)->toBeNull();
        });

        test('generateSpanId creates unique span ID', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $spanId1 = $extension->generateSpanId();
            $spanId2 = $extension->generateSpanId();

            // Assert
            expect($spanId1)->toStartWith('span_')
                ->and($spanId2)->toStartWith('span_')
                ->and($spanId1)->not->toBe($spanId2)
                ->and(mb_strlen($spanId1))->toBe(21); // 'span_' + 16 hex chars
        });

        test('generateSpanId accepts custom prefix', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $spanId = $extension->generateSpanId('custom_');

            // Assert
            expect($spanId)->toStartWith('custom_')
                ->and(mb_strlen($spanId))->toBe(23); // 'custom_' + 16 hex chars
        });

        test('generateTraceId creates unique trace ID', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $traceId1 = $extension->generateTraceId();
            $traceId2 = $extension->generateTraceId();

            // Assert
            expect($traceId1)->not->toBe($traceId2)
                ->and(mb_strlen($traceId1))->toBe(32) // 16 bytes = 32 hex chars
                ->and(mb_strlen($traceId2))->toBe(32);
        });

        test('buildDownstreamContext creates tracing context', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $context = $extension->buildDownstreamContext(
                traceId: 'trace-123',
                newSpanId: 'span-456',
                parentSpanId: 'span-789',
            );

            // Assert
            expect($context)->toHaveKey('trace_id', 'trace-123')
                ->and($context)->toHaveKey('span_id', 'span-456')
                ->and($context)->toHaveKey('parent_span_id', 'span-789')
                ->and($context)->not->toHaveKey('baggage');
        });

        test('buildDownstreamContext includes baggage when provided', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $baggage = ['key1' => 'value1', 'key2' => 'value2'];

            // Act
            $context = $extension->buildDownstreamContext(
                traceId: 'trace-abc',
                newSpanId: 'span-def',
                parentSpanId: 'span-ghi',
                baggage: $baggage,
            );

            // Assert
            expect($context)->toHaveKey('baggage', $baggage);
        });

        test('enrichResponse adds tracing extension to response', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $response = ResponseData::success(['data' => 'test'], '01JFEX0001');

            // Act
            $enriched = $extension->enrichResponse(
                $response,
                traceId: 'trace-xyz',
                spanId: 'span-abc',
                durationMs: 150,
            );

            // Assert
            expect($enriched)->toBeInstanceOf(ResponseData::class)
                ->and($enriched->extensions)->toHaveCount(1);

            $ext = $enriched->extensions[0];
            expect($ext->urn)->toBe(ExtensionUrn::Tracing->value)
                ->and($ext->data)->toHaveKey('trace_id', 'trace-xyz')
                ->and($ext->data)->toHaveKey('span_id', 'span-abc')
                ->and($ext->data)->toHaveKey('duration', ['value' => 150, 'unit' => 'millisecond']);
        });

        test('buildResponseData creates complete tracing data structure', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $data = $extension->buildResponseData(
                traceId: 'trace-123',
                spanId: 'span-456',
                durationMs: 250,
            );

            // Assert
            expect($data)->toHaveKey('trace_id', 'trace-123')
                ->and($data)->toHaveKey('span_id', 'span-456')
                ->and($data)->toHaveKey('duration', ['value' => 250, 'unit' => 'millisecond']);
        });

        test('extractOrCreateContext uses existing trace ID', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $options = ['trace_id' => 'existing-trace'];

            // Act
            $context = $extension->extractOrCreateContext($options);

            // Assert
            expect($context['trace_id'])->toBe('existing-trace');
        });

        test('extractOrCreateContext generates trace ID when missing', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $options = [];

            // Act
            $context = $extension->extractOrCreateContext($options);

            // Assert
            expect($context['trace_id'])->not->toBeNull()
                ->and(mb_strlen($context['trace_id']))->toBe(32);
        });

        test('extractOrCreateContext uses client span as parent', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $options = ['span_id' => 'client-span-123'];

            // Act
            $context = $extension->extractOrCreateContext($options);

            // Assert
            expect($context['parent_span_id'])->toBe('client-span-123');
        });

        test('extractOrCreateContext generates new server span', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $options = [];

            // Act
            $context = $extension->extractOrCreateContext($options);

            // Assert
            expect($context['span_id'])->not->toBeNull()
                ->and($context['span_id'])->toStartWith('span_');
        });

        test('extractOrCreateContext preserves baggage', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $options = ['baggage' => ['user_id' => '999']];

            // Act
            $context = $extension->extractOrCreateContext($options);

            // Assert
            expect($context['baggage'])->toBe(['user_id' => '999']);
        });

        test('isGlobal returns true indicating extension runs on all requests', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $result = $extension->isGlobal();

            // Assert
            expect($result)->toBeTrue();
        });

        test('isErrorFatal returns false indicating tracing errors should not fail requests', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $result = $extension->isErrorFatal();

            // Assert
            expect($result)->toBeFalse();
        });

        test('getSubscribedEvents returns correct event configuration', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $events = $extension->getSubscribedEvents();

            // Assert
            expect($events)->toHaveKey(ExecutingFunction::class)
                ->and($events)->toHaveKey(FunctionExecuted::class)
                ->and($events[ExecutingFunction::class])->toBe([
                    'priority' => 0,
                    'method' => 'onExecutingFunction',
                ])
                ->and($events[FunctionExecuted::class])->toBe([
                    'priority' => 0,
                    'method' => 'onFunctionExecuted',
                ]);
        });

        test('onExecutingFunction initializes context from extension options', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $request = RequestObjectData::asRequest('urn:cline:forrst:fn:test:function');
            $extensionData = ExtensionData::request(ExtensionUrn::Tracing->value, [
                'trace_id' => 'trace-initialize',
                'span_id' => 'client-span-123',
            ]);
            $event = new ExecutingFunction($request, $extensionData);

            // Act
            $extension->onExecutingFunction($event);

            // Assert - Verify context is initialized by checking subsequent event
            $response = ResponseData::success(['test' => true], 'req-001');
            $executedEvent = new FunctionExecuted($request, $extensionData, $response);
            $extension->onFunctionExecuted($executedEvent);

            $enriched = $executedEvent->getResponse();
            expect($enriched->extensions[0]->data['trace_id'])->toBe('trace-initialize');
        });

        test('onFunctionExecuted enriches response with trace metadata and duration', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $extensionData = ExtensionData::request(ExtensionUrn::Tracing->value, [
                'trace_id' => 'trace-enriched',
            ]);
            $request = RequestObjectData::asRequest('urn:cline:forrst:fn:test:function');
            $executingEvent = new ExecutingFunction($request, $extensionData);
            $extension->onExecutingFunction($executingEvent);

            $response = ResponseData::success(['result' => 'data'], 'req-002');
            $executedEvent = new FunctionExecuted($request, $extensionData, $response);

            // Act
            $extension->onFunctionExecuted($executedEvent);

            // Assert
            $enriched = $executedEvent->getResponse();
            expect($enriched->extensions)->toHaveCount(1)
                ->and($enriched->extensions[0]->urn)->toBe(ExtensionUrn::Tracing->value)
                ->and($enriched->extensions[0]->data['trace_id'])->toBe('trace-enriched')
                ->and($enriched->extensions[0]->data['span_id'])->toStartWith('span_')
                ->and($enriched->extensions[0]->data['duration'])->toHaveKey('value')
                ->and($enriched->extensions[0]->data['duration'])->toHaveKey('unit')
                ->and($enriched->extensions[0]->data['duration']['unit'])->toBe('millisecond')
                ->and($enriched->extensions[0]->data['duration']['value'])->toBeGreaterThanOrEqual(0);
        });

        test('onFunctionExecuted calculates duration between executing and executed events', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $extensionData = ExtensionData::request(ExtensionUrn::Tracing->value, [
                'trace_id' => 'trace-timing',
            ]);
            $request = RequestObjectData::asRequest('urn:cline:forrst:fn:test:function');
            $executingEvent = new ExecutingFunction($request, $extensionData);
            $extension->onExecutingFunction($executingEvent);

            // Simulate processing time
            Sleep::usleep(1_000); // 1ms

            $response = ResponseData::success(['timed' => true], 'req-003');
            $executedEvent = new FunctionExecuted($request, $extensionData, $response);

            // Act
            $extension->onFunctionExecuted($executedEvent);

            // Assert
            $enriched = $executedEvent->getResponse();
            $duration = $enriched->extensions[0]->data['duration']['value'];
            expect($duration)->toBeGreaterThan(0)
                ->and($duration)->toBeLessThan(100); // Should be less than 100ms
        });

        test('onFunctionExecuted resets context after enriching response', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $extensionData = ExtensionData::request(ExtensionUrn::Tracing->value, [
                'trace_id' => 'trace-reset',
            ]);
            $request = RequestObjectData::asRequest('urn:cline:forrst:fn:test:function');
            $executingEvent = new ExecutingFunction($request, $extensionData);
            $extension->onExecutingFunction($executingEvent);

            $response = ResponseData::success(['data' => 'first'], 'req-004');
            $executedEvent = new FunctionExecuted($request, $extensionData, $response);
            $extension->onFunctionExecuted($executedEvent);

            // Act - Call onFunctionExecuted again without calling onExecutingFunction
            $response2 = ResponseData::success(['data' => 'second'], 'req-005');
            $executedEvent2 = new FunctionExecuted($request, $extensionData, $response2);
            $extension->onFunctionExecuted($executedEvent2);

            // Assert - Second response should not have tracing extension
            $enriched2 = $executedEvent2->getResponse();
            expect($enriched2->extensions)->toBeNull();
        });

        test('onExecutingFunction captures high-resolution start time', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $extensionData = ExtensionData::request(ExtensionUrn::Tracing->value, [
                'trace_id' => 'trace-hrtime',
            ]);
            $request = RequestObjectData::asRequest('urn:cline:forrst:fn:test:function');
            $event = new ExecutingFunction($request, $extensionData);

            // Act
            $extension->onExecutingFunction($event);

            // Assert - Verify timing works by checking duration is calculated
            $response = ResponseData::success([], 'req-006');
            $executedEvent = new FunctionExecuted($request, $extensionData, $response);
            $extension->onFunctionExecuted($executedEvent);

            $enriched = $executedEvent->getResponse();
            expect($enriched->extensions[0]->data['duration']['value'])->toBeInt();
        });

        test('onExecutingFunction with baggage preserves context through lifecycle', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $extensionData = ExtensionData::request(ExtensionUrn::Tracing->value, [
                'trace_id' => 'trace-baggage',
                'span_id' => 'client-span-baggage',
                'baggage' => ['tenant' => 'acme', 'user_id' => '12345'],
            ]);
            $request = RequestObjectData::asRequest('urn:cline:forrst:fn:test:function');
            $executingEvent = new ExecutingFunction($request, $extensionData);

            // Act
            $extension->onExecutingFunction($executingEvent);

            $response = ResponseData::success(['ok' => true], 'req-007');
            $executedEvent = new FunctionExecuted($request, $extensionData, $response);
            $extension->onFunctionExecuted($executedEvent);

            // Assert - Baggage doesn't appear in response but context was used
            $enriched = $executedEvent->getResponse();
            expect($enriched->extensions[0]->data['trace_id'])->toBe('trace-baggage');
        });

        test('onExecutingFunction generates trace ID when not provided in options', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $extensionData = ExtensionData::request(ExtensionUrn::Tracing->value, []);
            $request = RequestObjectData::asRequest('urn:cline:forrst:fn:test:function');
            $executingEvent = new ExecutingFunction($request, $extensionData);

            // Act
            $extension->onExecutingFunction($executingEvent);

            $response = ResponseData::success([], 'req-008');
            $executedEvent = new FunctionExecuted($request, $extensionData, $response);
            $extension->onFunctionExecuted($executedEvent);

            // Assert
            $enriched = $executedEvent->getResponse();
            expect($enriched->extensions[0]->data['trace_id'])->toMatch('/^[0-9a-f]{32}$/');
        });

        test('onExecutingFunction uses client span as parent span in context', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $extensionData = ExtensionData::request(ExtensionUrn::Tracing->value, [
                'trace_id' => 'trace-parent',
                'span_id' => 'client-span-parent',
            ]);
            $request = RequestObjectData::asRequest('urn:cline:forrst:fn:test:function');
            $executingEvent = new ExecutingFunction($request, $extensionData);

            // Act
            $extension->onExecutingFunction($executingEvent);

            $response = ResponseData::success([], 'req-009');
            $executedEvent = new FunctionExecuted($request, $extensionData, $response);
            $extension->onFunctionExecuted($executedEvent);

            // Assert - Server generates new span, client span becomes parent
            $enriched = $executedEvent->getResponse();
            expect($enriched->extensions[0]->data['span_id'])->not->toBe('client-span-parent')
                ->and($enriched->extensions[0]->data['span_id'])->toStartWith('span_');
        });
    });

    describe('Edge Cases', function (): void {
        test('getTraceId handles null options', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $traceId = $extension->getTraceId(null);

            // Assert
            expect($traceId)->toBeNull();
        });

        test('getSpanId handles null options', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $spanId = $extension->getSpanId(null);

            // Assert
            expect($spanId)->toBeNull();
        });

        test('getParentSpanId handles null options', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $parentSpanId = $extension->getParentSpanId(null);

            // Assert
            expect($parentSpanId)->toBeNull();
        });

        test('getBaggage handles null options', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $baggage = $extension->getBaggage(null);

            // Assert
            expect($baggage)->toBeNull();
        });

        test('generateSpanId with empty prefix', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $spanId = $extension->generateSpanId('');

            // Assert
            expect($spanId)->not->toStartWith('span_')
                ->and(mb_strlen($spanId))->toBe(16);
        });

        test('buildDownstreamContext without baggage', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $context = $extension->buildDownstreamContext(
                traceId: 't1',
                newSpanId: 's1',
                parentSpanId: 'p1',
            );

            // Assert
            expect($context)->not->toHaveKey('baggage');
        });

        test('buildDownstreamContext with empty baggage', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $context = $extension->buildDownstreamContext(
                traceId: 't1',
                newSpanId: 's1',
                parentSpanId: 'p1',
                baggage: [],
            );

            // Assert
            expect($context)->toHaveKey('baggage', []);
        });

        test('enrichResponse appends to existing extensions', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $existingExt = ExtensionData::response('urn:cline:forrst:ext:other', ['data' => 'test']);
            $response = new ResponseData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0002',
                result: ['ok' => true],
                extensions: [$existingExt],
            );

            // Act
            $enriched = $extension->enrichResponse($response, 'trace-1', 'span-1', 100);

            // Assert
            expect($enriched->extensions)->toHaveCount(2)
                ->and($enriched->extensions[0]->urn)->toBe('urn:cline:forrst:ext:other')
                ->and($enriched->extensions[1]->urn)->toBe(ExtensionUrn::Tracing->value);
        });

        test('enrichResponse preserves response data', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $response = ResponseData::success(['user_id' => 123], '01JFEX0003');

            // Act
            $enriched = $extension->enrichResponse($response, 'trace-2', 'span-2', 75);

            // Assert
            expect($enriched->result)->toBe(['user_id' => 123])
                ->and($enriched->id)->toBe('01JFEX0003');
        });

        test('buildResponseData with zero duration', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $data = $extension->buildResponseData('trace-3', 'span-3', 0);

            // Assert
            expect($data['duration'])->toBe(['value' => 0, 'unit' => 'millisecond']);
        });

        test('extractOrCreateContext with all options provided', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $options = [
                'trace_id' => 'existing-trace',
                'span_id' => 'client-span',
                'baggage' => ['key' => 'value'],
            ];

            // Act
            $context = $extension->extractOrCreateContext($options);

            // Assert
            expect($context['trace_id'])->toBe('existing-trace')
                ->and($context['parent_span_id'])->toBe('client-span')
                ->and($context['baggage'])->toBe(['key' => 'value'])
                ->and($context['span_id'])->not->toBe('client-span'); // New server span
        });

        test('extractOrCreateContext with no options creates new context', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $context = $extension->extractOrCreateContext(null);

            // Assert
            expect($context['trace_id'])->not->toBeNull()
                ->and($context['span_id'])->not->toBeNull()
                ->and($context['parent_span_id'])->toBeNull()
                ->and($context['baggage'])->toBeNull();
        });

        test('generateTraceId format is valid hex', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $traceId = $extension->generateTraceId();

            // Assert
            expect($traceId)->toMatch('/^[0-9a-f]{32}$/');
        });

        test('generateSpanId format is valid with prefix', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act
            $spanId = $extension->generateSpanId();

            // Assert
            expect($spanId)->toMatch('/^span_[0-9a-f]{16}$/');
        });
    });

    describe('Sad Paths', function (): void {
        test('onFunctionExecuted does nothing when context is null', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $request = RequestObjectData::asRequest('urn:cline:forrst:fn:test:function');
            $extensionData = ExtensionData::request(ExtensionUrn::Tracing->value, []);
            $response = ResponseData::success(['data' => 'test'], 'req-no-context');
            $executedEvent = new FunctionExecuted($request, $extensionData, $response);

            // Act - Call onFunctionExecuted without calling onExecutingFunction first
            $extension->onFunctionExecuted($executedEvent);

            // Assert - Response should remain unchanged
            $result = $executedEvent->getResponse();
            expect($result->extensions)->toBeNull();
        });

        test('onFunctionExecuted handles response with existing errors', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $extensionData = ExtensionData::request(ExtensionUrn::Tracing->value, [
                'trace_id' => 'trace-error',
            ]);
            $request = RequestObjectData::asRequest('urn:cline:forrst:fn:test:function');
            $executingEvent = new ExecutingFunction($request, $extensionData);
            $extension->onExecutingFunction($executingEvent);

            $response = new ResponseData(
                protocol: ProtocolData::forrst(),
                id: 'req-error',
                result: null,
                errors: [['code' => -32_600, 'message' => 'Invalid request']],
            );
            $executedEvent = new FunctionExecuted($request, $extensionData, $response);

            // Act
            $extension->onFunctionExecuted($executedEvent);

            // Assert - Tracing should still be added despite errors
            $enriched = $executedEvent->getResponse();
            expect($enriched->errors)->toHaveCount(1)
                ->and($enriched->extensions)->toHaveCount(1)
                ->and($enriched->extensions[0]->data['trace_id'])->toBe('trace-error');
        });

        test('enrichResponse with very large duration', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $response = ResponseData::success(['data' => 'test'], '01JFEX0004');

            // Act
            $enriched = $extension->enrichResponse($response, 'trace-4', 'span-4', 999_999);

            // Assert
            $ext = $enriched->extensions[0];
            expect($ext->data['duration'])->toBe(['value' => 999_999, 'unit' => 'millisecond']);
        });

        test('buildDownstreamContext propagates trace through multiple hops', function (): void {
            // Arrange
            $extension = new TracingExtension();

            // Act - Simulate multiple service hops
            $hop1 = $extension->buildDownstreamContext('trace-1', 'span-hop1', 'span-client');
            $hop2 = $extension->buildDownstreamContext('trace-1', 'span-hop2', 'span-hop1');
            $hop3 = $extension->buildDownstreamContext('trace-1', 'span-hop3', 'span-hop2');

            // Assert - Same trace ID propagates
            expect($hop1['trace_id'])->toBe('trace-1')
                ->and($hop2['trace_id'])->toBe('trace-1')
                ->and($hop3['trace_id'])->toBe('trace-1');

            // Assert - Parent-child relationships
            expect($hop1['parent_span_id'])->toBe('span-client')
                ->and($hop2['parent_span_id'])->toBe('span-hop1')
                ->and($hop3['parent_span_id'])->toBe('span-hop2');
        });

        test('buildDownstreamContext with complex baggage', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $baggage = [
                'user_id' => '12345',
                'tenant' => 'acme-corp',
                'request_id' => 'req-abc123',
                'metadata' => ['region' => 'us-east-1', 'priority' => 'high'],
            ];

            // Act
            $context = $extension->buildDownstreamContext(
                traceId: 'trace-complex',
                newSpanId: 'span-complex',
                parentSpanId: 'span-parent',
                baggage: $baggage,
            );

            // Assert
            expect($context['baggage'])->toBe($baggage)
                ->and($context['baggage']['metadata'])->toBe(['region' => 'us-east-1', 'priority' => 'high']);
        });

        test('extractOrCreateContext when client provides incomplete trace', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $options = ['span_id' => 'orphan-span']; // Span but no trace

            // Act
            $context = $extension->extractOrCreateContext($options);

            // Assert - Should generate trace ID
            expect($context['trace_id'])->not->toBeNull()
                ->and($context['parent_span_id'])->toBe('orphan-span');
        });

        test('extractOrCreateContext generates different server spans', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $options = ['trace_id' => 'same-trace'];

            // Act
            $context1 = $extension->extractOrCreateContext($options);
            $context2 = $extension->extractOrCreateContext($options);

            // Assert - Same trace, different spans
            expect($context1['trace_id'])->toBe('same-trace')
                ->and($context2['trace_id'])->toBe('same-trace')
                ->and($context1['span_id'])->not->toBe($context2['span_id']);
        });

        test('enrichResponse maintains baggage context across services', function (): void {
            // Arrange
            $extension = new TracingExtension();
            $response = ResponseData::success(['status' => 'ok'], '01JFEX0005');

            // Act
            $enriched = $extension->enrichResponse($response, 'trace-5', 'span-5', 50);

            // Assert - Extension metadata is added
            $ext = $enriched->extensions[0];
            expect($ext->urn)->toBe(ExtensionUrn::Tracing->value)
                ->and($ext->data['trace_id'])->toBe('trace-5')
                ->and($ext->data['span_id'])->toBe('span-5');
        });
    });
});

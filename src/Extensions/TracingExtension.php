<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions;

use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Events\ExecutingFunction;
use Cline\Forrst\Events\FunctionExecuted;
use Override;

use function assert;
use function bin2hex;
use function hrtime;
use function is_string;
use function random_bytes;
use function round;

/**
 * Distributed tracing extension handler for Forrst protocol.
 *
 * Implements distributed tracing functionality across Forrst function calls, enabling
 * end-to-end request tracking across service boundaries. Propagates trace IDs, manages
 * span hierarchies, transmits baggage context, and measures operation durations.
 *
 * This extension operates in GLOBAL mode, running on all requests to ensure complete
 * trace coverage without requiring client opt-in. Errors are non-fatal to prevent
 * tracing infrastructure failures from disrupting functional requests.
 *
 * Trace context flows through requests and responses, with server-generated span IDs
 * correlating to client span IDs as parent relationships. High-resolution timing
 * captures server-side processing duration for performance analysis.
 *
 * Request options structure:
 * - trace_id: string - Root trace identifier for the entire operation
 * - span_id: string - Client span identifier that becomes parent_span_id
 * - parent_span_id: string - Parent span identifier in the trace hierarchy
 * - baggage: object - Key-value context propagated across service boundaries
 *
 * Response data structure:
 * - trace_id: string - Echoed trace ID for correlation
 * - span_id: string - Server-generated span ID for this operation
 * - duration: object - Processing duration with value and unit (milliseconds)
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/tracing
 * @see https://docs.cline.sh/forrst/protocol
 */
final class TracingExtension extends AbstractExtension
{
    /**
     * Tracing context for the current request lifecycle.
     *
     * Stores trace ID, server span ID, parent span ID, baggage context, and
     * high-resolution start time for duration calculation. Reset to null after
     * response enrichment to prevent context leakage between requests.
     *
     * @var null|array{trace_id: string, span_id: string, parent_span_id: ?string, baggage: ?array<string, mixed>, start_time: int}
     */
    private ?array $context = null;

    /**
     * Get the URN identifying this extension.
     *
     * @return string The tracing extension URN from the extension registry
     */
    #[Override()]
    public function getUrn(): string
    {
        return ExtensionUrn::Tracing->value;
    }

    /**
     * Indicate this extension runs on all requests.
     *
     * Tracing operates globally to ensure complete trace coverage without
     * requiring explicit client opt-in for every request.
     *
     * @return bool Always true for global execution
     */
    #[Override()]
    public function isGlobal(): bool
    {
        return true;
    }

    /**
     * Indicate tracing errors should not fail requests.
     *
     * Tracing infrastructure failures must not disrupt functional operations,
     * ensuring observability issues don't impact service availability.
     *
     * @return bool Always false for non-fatal error handling
     */
    #[Override()]
    public function isErrorFatal(): bool
    {
        return false;
    }

    /**
     * Get event subscriptions for this extension.
     *
     * Subscribes to ExecutingFunction at priority 0 to initialize trace context
     * and start timing, then FunctionExecuted at priority 0 to enrich responses
     * with tracing metadata and duration measurements.
     *
     * @return array<string, array{priority: int, method: string}> Event subscription configuration
     */
    #[Override()]
    public function getSubscribedEvents(): array
    {
        return [
            ExecutingFunction::class => [
                'priority' => 0,
                'method' => 'onExecutingFunction',
            ],
            FunctionExecuted::class => [
                'priority' => 0,
                'method' => 'onFunctionExecuted',
            ],
        ];
    }

    /**
     * Initialize tracing context and start timing before function execution.
     *
     * Extracts or generates trace context from request extension options and
     * captures high-resolution start time for duration measurement. Context
     * includes trace ID, span IDs, parent relationships, and baggage.
     *
     * @param ExecutingFunction $event The executing function event with extension options
     */
    public function onExecutingFunction(ExecutingFunction $event): void
    {
        $options = $event->extension->options;

        $this->context = [
            ...$this->extractOrCreateContext($options),
            'start_time' => hrtime(true),
        ];
    }

    /**
     * Enrich response with tracing metadata after function execution.
     *
     * Calculates processing duration from high-resolution timer, attaches trace
     * extension data to response, and resets context to prevent request leakage.
     *
     * @param FunctionExecuted $event The function executed event containing the response
     */
    public function onFunctionExecuted(FunctionExecuted $event): void
    {
        if ($this->context === null) {
            return;
        }

        $durationNs = hrtime(true) - $this->context['start_time'];
        $durationMs = (int) round($durationNs / 1_000_000);

        $event->setResponse($this->enrichResponse(
            $event->getResponse(),
            $this->context['trace_id'],
            $this->context['span_id'],
            $durationMs,
        ));

        $this->context = null;
    }

    /**
     * Extract trace ID from extension options.
     *
     * @param  null|array<string, mixed> $options Extension options from request
     * @return null|string               Root trace identifier or null if not provided
     */
    public function getTraceId(?array $options): ?string
    {
        $value = $options['trace_id'] ?? null;
        assert($value === null || is_string($value));

        return $value;
    }

    /**
     * Extract span ID from extension options.
     *
     * @param  null|array<string, mixed> $options Extension options from request
     * @return null|string               Client span identifier or null if not provided
     */
    public function getSpanId(?array $options): ?string
    {
        $value = $options['span_id'] ?? null;
        assert($value === null || is_string($value));

        return $value;
    }

    /**
     * Extract parent span ID from extension options.
     *
     * @param  null|array<string, mixed> $options Extension options from request
     * @return null|string               Parent span identifier or null if not provided
     */
    public function getParentSpanId(?array $options): ?string
    {
        $value = $options['parent_span_id'] ?? null;
        assert($value === null || is_string($value));

        return $value;
    }

    /**
     * Extract baggage context from extension options.
     *
     * @param  null|array<string, mixed> $options Extension options from request
     * @return null|array<string, mixed> Baggage key-value pairs or null if not provided
     */
    public function getBaggage(?array $options): ?array
    {
        // @phpstan-ignore return.type
        return $options['baggage'] ?? null;
    }

    /**
     * Generate a cryptographically random span ID.
     *
     * @param  string $prefix Optional prefix for the span ID (default: 'span_')
     * @return string 16-character hex span ID with optional prefix
     */
    public function generateSpanId(string $prefix = 'span_'): string
    {
        return $prefix.bin2hex(random_bytes(8));
    }

    /**
     * Generate a cryptographically random trace ID.
     *
     * @return string 32-character hex trace ID suitable for distributed tracing
     */
    public function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Build tracing context for downstream service calls.
     *
     * Constructs trace extension options for propagating trace context to downstream
     * Forrst calls, maintaining trace hierarchy by setting current span as parent.
     *
     * @param  string                    $traceId      Root trace ID to propagate
     * @param  string                    $newSpanId    New span ID for the downstream operation
     * @param  string                    $parentSpanId Current span ID that becomes the parent
     * @param  null|array<string, mixed> $baggage      Optional baggage context to propagate
     * @return array<string, mixed>      Tracing extension options for downstream request
     */
    public function buildDownstreamContext(
        string $traceId,
        string $newSpanId,
        string $parentSpanId,
        ?array $baggage = null,
    ): array {
        $context = [
            'trace_id' => $traceId,
            'span_id' => $newSpanId,
            'parent_span_id' => $parentSpanId,
        ];

        if ($baggage !== null) {
            $context['baggage'] = $baggage;
        }

        return $context;
    }

    /**
     * Enrich a response with tracing extension metadata.
     *
     * Creates a new ResponseData instance with trace extension appended, including
     * trace ID for correlation, server span ID, and processing duration. Preserves
     * all original response properties.
     *
     * @param  ResponseData $response   Original response to enrich
     * @param  string       $traceId    Trace ID for correlation
     * @param  string       $spanId     Server-generated span ID for this operation
     * @param  int          $durationMs Processing duration in milliseconds
     * @return ResponseData New response with tracing metadata attached
     */
    public function enrichResponse(
        ResponseData $response,
        string $traceId,
        string $spanId,
        int $durationMs,
    ): ResponseData {
        $extensions = $response->extensions ?? [];
        $extensions[] = ExtensionData::response(ExtensionUrn::Tracing->value, [
            'trace_id' => $traceId,
            'span_id' => $spanId,
            'duration' => [
                'value' => $durationMs,
                'unit' => 'millisecond',
            ],
        ]);

        return new ResponseData(
            protocol: $response->protocol,
            id: $response->id,
            result: $response->result,
            errors: $response->errors,
            extensions: $extensions,
            meta: $response->meta,
        );
    }

    /**
     * Build tracing response data structure.
     *
     * Constructs the trace extension response payload with trace ID, span ID,
     * and duration measurement formatted for Forrst protocol responses.
     *
     * @param  string               $traceId    Trace ID for correlation
     * @param  string               $spanId     Server span ID for this operation
     * @param  int                  $durationMs Processing duration in milliseconds
     * @return array<string, mixed> Structured tracing response data
     */
    public function buildResponseData(string $traceId, string $spanId, int $durationMs): array
    {
        return [
            'trace_id' => $traceId,
            'span_id' => $spanId,
            'duration' => [
                'value' => $durationMs,
                'unit' => 'millisecond',
            ],
        ];
    }

    /**
     * Extract or create trace context from request extension options.
     *
     * Retrieves trace ID and span ID from request if provided, otherwise generates
     * new IDs. Client span ID becomes parent span ID, new server span ID is generated.
     * Preserves baggage context for cross-service propagation.
     *
     * @param  null|array<string, mixed>                                                                         $options Extension options from request
     * @return array{trace_id: string, span_id: string, parent_span_id: ?string, baggage: ?array<string, mixed>} Complete trace context
     */
    public function extractOrCreateContext(?array $options): array
    {
        $traceId = $this->getTraceId($options) ?? $this->generateTraceId();
        $clientSpanId = $this->getSpanId($options);
        $serverSpanId = $this->generateSpanId();

        return [
            'trace_id' => $traceId,
            'span_id' => $serverSpanId,
            'parent_span_id' => $clientSpanId,
            'baggage' => $this->getBaggage($options),
        ];
    }
}

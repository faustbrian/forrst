<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Streaming;

use BackedEnum;
use JsonException;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

use function implode;
use function json_encode;
use function round;

/**
 * Represents a chunk in a streaming response.
 *
 * StreamChunks are yielded by StreamableFunction::stream() and converted
 * to SSE (Server-Sent Events) format by the StreamController. Each chunk
 * represents a discrete event in the stream with typed event names, payload
 * data, and metadata for client-side stream handling.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/extensions/stream
 * @psalm-immutable
 */
final readonly class StreamChunk
{
    /**
     * Standard SSE event type constants for stream chunks.
     *
     * Used to categorize chunk events for client-side handling and routing.
     */
    public const string EVENT_DATA = 'data';

    public const string EVENT_PROGRESS = 'progress';

    public const string EVENT_ERROR = 'error';

    public const string EVENT_DONE = 'done';

    /**
     * Create a new stream chunk.
     *
     * @param mixed       $data  Chunk payload data to be JSON-encoded and sent to the client.
     *                           Can be any JSON-serializable value including arrays, objects,
     *                           strings, numbers, or null.
     * @param string      $event SSE event type identifier. Determines how clients handle the
     *                           chunk. Use EVENT_* constants for standard event types.
     * @param bool        $final Whether this chunk signals the end of the stream. When true,
     *                           the connection will be closed after sending this chunk.
     * @param null|string $id    Optional SSE event ID for client-side event tracking and
     *                           reconnection handling. Useful for resumable streams.
     */
    public function __construct(
        public mixed $data,
        public string $event = self::EVENT_DATA,
        public bool $final = false,
        public ?string $id = null,
    ) {}

    /**
     * Create a data chunk for partial results.
     *
     * Factory method for creating standard data event chunks. Use this for
     * streaming partial results, intermediate values, or any incremental data
     * that doesn't fit other event types.
     *
     * @param  mixed $data Chunk payload containing partial or incremental data
     * @return self  New StreamChunk instance with EVENT_DATA type
     */
    public static function data(mixed $data): self
    {
        return new self(data: $data, event: self::EVENT_DATA);
    }

    /**
     * Create a progress update chunk with percentage calculation.
     *
     * Factory method for creating progress event chunks with standardized structure
     * containing current value, total, calculated percentage, and optional message.
     * Use this for long-running operations to provide user feedback.
     *
     * @param  int         $current Current progress value (e.g., items processed)
     * @param  int         $total   Total value for 100% completion
     * @param  null|string $message Optional descriptive message about current progress state
     * @return self        New StreamChunk with EVENT_PROGRESS type and structured progress data
     */
    public static function progress(int $current, int $total, ?string $message = null): self
    {
        return new self(
            data: [
                'current' => $current,
                'total' => $total,
                'percent' => $total > 0 ? (int) round(($current / $total) * 100) : 0,
                'message' => $message,
            ],
            event: self::EVENT_PROGRESS,
        );
    }

    /**
     * Create an error chunk that terminates the stream.
     *
     * Factory method for creating error event chunks with structured error information.
     * Error chunks automatically have final=true, closing the stream after sending.
     * Use this to communicate failures during stream processing.
     *
     * @param  BackedEnum|string $code    Machine-readable error code for client-side error handling
     * @param  string            $message Human-readable error description
     * @param  null|mixed        $details Optional additional context or debugging information about the error
     * @return self              New StreamChunk with EVENT_ERROR type marked as final
     */
    public static function error(string|BackedEnum $code, string $message, mixed $details = null): self
    {
        return new self(
            data: [
                'code' => $code instanceof BackedEnum ? $code->value : $code,
                'message' => $message,
                'details' => $details,
            ],
            event: self::EVENT_ERROR,
            final: true,
        );
    }

    /**
     * Create the final completion chunk.
     *
     * Factory method for creating done event chunks that signal successful stream
     * completion. Done chunks automatically have final=true. Use this to send the
     * final result and close the stream gracefully.
     *
     * @param  mixed $result Final result data to send with completion event, or null if no result
     * @return self  New StreamChunk with EVENT_DONE type marked as final
     */
    public static function done(mixed $result = null): self
    {
        return new self(
            data: $result,
            event: self::EVENT_DONE,
            final: true,
        );
    }

    /**
     * Format chunk as Server-Sent Events (SSE) protocol string.
     *
     * Converts the chunk into SSE wire format with optional ID, event type,
     * and JSON-encoded data fields. The output conforms to the SSE specification
     * with newline-delimited fields and double-newline termination.
     *
     * @throws JsonException If data cannot be JSON-encoded
     * @return string        SSE-formatted event string ready for transmission
     */
    public function toSse(): string
    {
        $lines = [];

        if ($this->id !== null) {
            $lines[] = 'id: '.$this->id;
        }

        $lines[] = 'event: '.$this->event;

        $json = json_encode($this->data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $lines[] = 'data: '.$json;

        return implode("\n", $lines)."\n\n";
    }
}

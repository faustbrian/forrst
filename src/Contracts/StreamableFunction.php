<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Contracts;

use Cline\Forrst\Streaming\StreamChunk;
use Generator;

/**
 * Forrst streamable function contract interface.
 *
 * Defines the contract for implementing RPC functions that support streaming
 * responses via Server-Sent Events (SSE). Streamable functions yield partial
 * results progressively, enabling real-time data transmission for long-running
 * operations or large datasets.
 *
 * Functions implementing this interface can stream partial results to clients
 * using Server-Sent Events. Streaming is negotiated via the stream extension
 * and only activated when both client and function support it. Functions must
 * yield StreamChunk objects or raw data that will be automatically wrapped.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/stream Stream extension specification
 */
interface StreamableFunction extends FunctionInterface
{
    /**
     * Stream the function response.
     *
     * Yields progressive chunks of the response data as a Generator. Each yielded
     * value must be a StreamChunk object and is sent to the client as a Server-Sent
     * Event. The final chunk should include `final: true` to signal stream completion.
     *
     * ERROR HANDLING: If an error occurs during streaming:
     * 1. Yield StreamChunk::error($code, $message)
     * 2. Yield StreamChunk::final() to close the stream
     * 3. Do NOT throw exceptions (connection may be open)
     *
     * @example Basic streaming
     * ```php
     * public function stream(): Generator {
     *     yield StreamChunk::data(['progress' => 0.25]);
     *     yield StreamChunk::data(['progress' => 0.50]);
     *     yield StreamChunk::data(['result' => $data], final: true);
     * }
     * ```
     *
     * @example Error handling during stream
     * ```php
     * public function stream(): Generator
     * {
     *     try {
     *         yield StreamChunk::data(['progress' => 0.25]);
     *         yield StreamChunk::data(['progress' => 0.50]);
     *
     *         $result = $this->processData(); // May throw
     *
     *         yield StreamChunk::data($result, final: true);
     *     } catch (\Exception $e) {
     *         yield StreamChunk::error('PROCESSING_ERROR', $e->getMessage());
     *         yield StreamChunk::final();
     *     }
     * }
     * ```
     *
     * @return Generator<int, StreamChunk> Yields StreamChunk objects as SSE events
     */
    public function stream(): Generator;
}

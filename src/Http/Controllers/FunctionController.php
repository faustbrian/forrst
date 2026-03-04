<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Http\Controllers;

use Cline\Forrst\Contracts\StreamableFunction;
use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Extensions\ExtensionUrn;
use Cline\Forrst\Extensions\StreamExtension;
use Cline\Forrst\Requests\RequestHandler;
use Cline\Forrst\Streaming\StreamChunk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Spatie\LaravelData\Data;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

use function connection_aborted;
use function flush;
use function get_debug_type;
use function is_array;
use function is_object;
use function json_encode;
use function method_exists;
use function ob_end_flush;
use function ob_flush;
use function ob_get_level;

/**
 * HTTP controller for handling Forrst function invocations.
 *
 * This controller serves as the entry point for all Forrst requests, receiving
 * HTTP requests, delegating processing to the RequestHandler, and formatting the
 * response according to Forrst protocol specifications. It acts as a bridge between
 * Laravel's HTTP layer and the Forrst protocol layer.
 *
 * Per the Forrst protocol specification, HTTP status codes use semantic meanings
 * to indicate the result of the operation (200 for success, 4xx for client errors,
 * 5xx for server errors). The response body contains detailed error information
 * when applicable, allowing clients to handle errors appropriately across all transports.
 *
 * Supports streaming responses via Server-Sent Events when the client requests
 * the stream extension and the function implements StreamableFunction. Streaming
 * enables real-time progress updates and incremental results.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/protocol
 * @see https://docs.cline.sh/forrst/extensions/stream
 *
 * @psalm-immutable
 */
final readonly class FunctionController
{
    /**
     * Handle the incoming Forrst request.
     *
     * Processes Forrst requests by extracting the raw request body, passing it
     * to the RequestHandler for parsing and function execution, and formatting the
     * result as a JSON response. For streaming requests, returns an SSE stream.
     *
     * Per Forrst specification, HTTP status codes use semantic meanings to indicate
     * the result of the operation. Success returns 200, client errors return 4xx,
     * and server errors return 5xx. Detailed error information is provided in the
     * response body's `error` or `errors` field.
     *
     * @param Request        $request        The incoming HTTP request containing
     *                                       the Forrst request payload in the body as
     *                                       JSON conforming to Forrst protocol
     * @param RequestHandler $requestHandler The request handler that parses Forrst
     *                                       requests, routes them to appropriate
     *                                       functions, and formats responses.
     *                                       Injected via Laravel's service container
     *
     * @return JsonResponse|StreamedResponse The Forrst response formatted as JSON
     *                                       (standard) or SSE stream (when streaming
     *                                       extension is enabled)
     */
    public function __invoke(Request $request, RequestHandler $requestHandler): JsonResponse|StreamedResponse
    {
        $content = $request->getContent();

        // Validate request has content
        if (empty($content)) {
            return Response::json([
                'protocol' => ['name' => 'forrst', 'version' => '1.0.0'],
                'id' => null,
                'errors' => [[
                    'status' => '400',
                    'code' => 'empty_request',
                    'title' => 'Empty request body',
                    'detail' => 'Request body cannot be empty. Expected JSON-encoded Forrst request.',
                ]],
            ], 400);
        }

        $result = $requestHandler->handle($content);

        // Check if streaming was requested and enabled
        $streamContext = StreamExtension::getContext();

        if ($streamContext !== null) {
            return $this->handleStreaming($streamContext);
        }

        if ($result->data instanceof Data) {
            return Response::json($result->data->toArray(), $result->statusCode);
        }

        if (is_array($result->data)) {
            return Response::json($result->data, $result->statusCode);
        }

        // Unexpected type - log and return error
        Log::error('Invalid result data type', [
            'type' => get_debug_type($result->data),
            'class' => is_object($result->data) ? $result->data::class : null,
        ]);

        return Response::json([
            'protocol' => ['name' => 'forrst', 'version' => '1.0.0'],
            'id' => null,
            'errors' => [[
                'status' => '500',
                'code' => 'internal_error',
                'title' => 'Invalid response data',
                'detail' => 'Server generated invalid response data type.',
            ]],
        ], 500);
    }

    /**
     * Handle a streaming request.
     *
     * Creates a Server-Sent Events response that streams chunks from the function's
     * stream() method. Handles connection management, error propagation, and sends
     * a final completion event when streaming finishes.
     *
     * @param array{enabled: bool, function: StreamableFunction, request: RequestObjectData} $context Stream
     *                                                                                                context containing the function to stream and request data
     *
     * @return StreamedResponse SSE streaming
     *                          response with proper headers for event streaming
     */
    private function handleStreaming(array $context): StreamedResponse
    {
        /** @var StreamableFunction $function */
        $function = $context['function'];
        $requestData = $context['request'];

        // Inject request into function
        $function->setRequest($requestData);

        return new StreamedResponse(
            function () use ($function, $requestData): void {
                $finalResponseSent = false;

                try {
                    // Disable output buffering for streaming
                    while (ob_get_level() > 0) {
                        ob_end_flush();
                    }

                    // Send initial connected event
                    echo StreamChunk::data(['status' => 'connected'])->toSse();
                    $this->flush();

                    foreach ($function->stream() as $chunk) {
                        // Check for client disconnect FIRST
                        if (connection_aborted() !== 0) {
                            // Cleanup: notify function of disconnect
                            if (method_exists($function, 'onDisconnect')) {
                                $function->onDisconnect();
                            }

                            break;
                        }

                        if (!$chunk instanceof StreamChunk) {
                            $chunk = StreamChunk::data($chunk);
                        }

                        echo $chunk->toSse();
                        $this->flush();

                        if ($chunk->final) {
                            break;
                        }
                    }
                } catch (Throwable $throwable) {
                    // Log error before sending to client
                    Log::error('Streaming error', [
                        'exception' => $throwable,
                        'function' => $function::class,
                    ]);

                    // Send error chunk
                    echo StreamChunk::error(
                        ErrorCode::InternalError,
                        $throwable->getMessage(),
                    )->toSse();
                    $this->flush();
                } finally {
                    // Always send final response if not already sent
                    $this->sendFinalResponse($requestData);
                }
            },
            \Symfony\Component\HttpFoundation\Response::HTTP_OK,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no', // Disable nginx buffering
            ],
        );
    }

    /**
     * Send the final Forrst response as an SSE event.
     *
     * Constructs and sends a complete Forrst response indicating the stream has
     * finished, including protocol metadata and stream extension completion data.
     *
     * @param RequestObjectData $requestData The original request data
     *                                       containing ID and metadata
     */
    private function sendFinalResponse(RequestObjectData $requestData): void
    {
        $response = new ResponseData(
            protocol: ProtocolData::forrst(),
            id: $requestData->id,
            result: ['streamed' => true],
            extensions: [
                new ExtensionData(
                    urn: ExtensionUrn::Stream,
                    data: ['completed' => true],
                ),
            ],
        );

        echo "event: complete\n";
        echo 'data: '.json_encode($response->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n\n";
        $this->flush();
    }

    /**
     * Flush output buffers to client.
     *
     * Ensures buffered output is sent immediately for real-time streaming.
     * Handles both PHP output buffering and web server buffering.
     */
    private function flush(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}

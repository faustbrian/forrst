<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions;

use Cline\Forrst\Contracts\StreamableFunction;
use Cline\Forrst\Data\ErrorData;
use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Events\ExecutingFunction;
use Cline\Forrst\Events\RequestValidated;
use Cline\Forrst\Facades\Server;
use Override;
use Throwable;

use function is_array;
use function request;

/**
 * Streaming extension handler for Forrst protocol SSE support.
 *
 * Enables Server-Sent Events (SSE) streaming for functions implementing the StreamableFunction
 * contract. Validates streaming capability during request validation, ensuring functions
 * support streaming before enabling it. Manages streaming context throughout request lifecycle.
 *
 * Clients opt into streaming by including the stream extension with accept: true in their
 * request. The extension validates the target function implements StreamableFunction and
 * stores streaming context in request attributes for the FunctionController to consume.
 *
 * Request options structure:
 * - accept: bool - Whether the client accepts streaming responses
 *
 * Response data structure:
 * - enabled: bool - Whether streaming was enabled for this request
 * - content_type: string - The SSE content type (text/event-stream)
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/stream
 * @see https://docs.cline.sh/forrst/protocol
 */
final class StreamExtension extends AbstractExtension
{
    /**
     * Request attribute key for streaming context storage.
     *
     * Used to store streaming metadata in the request attributes bag,
     * including enabled status, function reference, and request object.
     */
    public const string CONTEXT_KEY = 'forrst.stream';

    /**
     * Check if the current request should use streaming response.
     *
     * Inspects request attributes for streaming context to determine if the
     * FunctionController should return an SSE stream instead of a JSON response.
     *
     * @return bool True if streaming is enabled for this request
     */
    public static function shouldStream(): bool
    {
        $context = request()->attributes->get(self::CONTEXT_KEY);

        return is_array($context) && ($context['enabled'] ?? false) === true;
    }

    /**
     * Get the streaming context metadata for the current request.
     *
     * Retrieves and validates the streaming context stored in request attributes,
     * including the enabled flag, StreamableFunction instance, and request object.
     * Returns null if streaming is not enabled or context is invalid.
     *
     * @return null|array{enabled: bool, function: StreamableFunction, request: RequestObjectData} Streaming context or null
     */
    public static function getContext(): ?array
    {
        $context = request()->attributes->get(self::CONTEXT_KEY);

        if (!is_array($context)) {
            return null;
        }

        // Validate context structure
        if (
            !isset($context['enabled'], $context['function'], $context['request'])
            || !$context['function'] instanceof StreamableFunction
            || !$context['request'] instanceof RequestObjectData
        ) {
            return null;
        }

        /** @var array{enabled: bool, function: StreamableFunction, request: RequestObjectData} $context */
        return $context;
    }

    /**
     * Get the URN identifying this extension.
     *
     * @return string The stream extension URN from the extension registry
     */
    #[Override()]
    public function getUrn(): string
    {
        return ExtensionUrn::Stream->value;
    }

    /**
     * Get event subscriptions for this extension.
     *
     * Subscribes to RequestValidated at priority 10 to validate streaming support early,
     * and ExecutingFunction at priority 10 to allow pre-streaming setup by other extensions.
     *
     * @return array<string, array{priority: int, method: string}> Event subscription configuration
     */
    #[Override()]
    public function getSubscribedEvents(): array
    {
        return [
            RequestValidated::class => [
                'priority' => 10,
                'method' => 'onRequestValidated',
            ],
            ExecutingFunction::class => [
                'priority' => 10,
                'method' => 'onExecutingFunction',
            ],
        ];
    }

    /**
     * Validate streaming capability during request validation phase.
     *
     * Checks if the client requested streaming via accept: true, then validates
     * the target function implements StreamableFunction. Returns error response
     * if streaming is requested for non-streamable functions.
     *
     * @param RequestValidated $event The request validation event
     */
    public function onRequestValidated(RequestValidated $event): void
    {
        $extension = $event->request->getExtension(ExtensionUrn::Stream->value);

        if (!$extension instanceof ExtensionData) {
            return;
        }

        $accept = $extension->options['accept'] ?? false;

        if ($accept !== true) {
            return;
        }

        // Get the function to check if it supports streaming
        $functionName = $event->request->getFunction();
        $functionVersion = $event->request->getVersion();

        try {
            $function = Server::getFunctionRepository()->resolve($functionName, $functionVersion);
        } catch (Throwable) {
            // Function doesn't exist - let normal validation handle it
            return;
        }

        if (!$function instanceof StreamableFunction) {
            $event->setResponse(ResponseData::error(
                new ErrorData(
                    code: ErrorCode::ExtensionNotApplicable,
                    message: 'Function does not support streaming',
                    details: [
                        'function' => $functionName,
                        'extension' => ExtensionUrn::Stream->value,
                    ],
                ),
                $event->request->id,
            ));
            $event->stopPropagation();

            return;
        }

        // Mark request for streaming
        request()->attributes->set(self::CONTEXT_KEY, [
            'enabled' => true,
            'function' => $function,
            'request' => $event->request,
        ]);
    }

    /**
     * Handle executing function event for streaming requests.
     *
     * Provides a pre-execution hook for streaming requests, allowing other extensions
     * to perform setup before the FunctionController initiates the SSE stream. The
     * actual streaming response is handled by FunctionController, not this event handler.
     *
     * @param ExecutingFunction $event The executing function event
     */
    public function onExecutingFunction(ExecutingFunction $event): void
    {
        if ($event->extension->urn !== ExtensionUrn::Stream->value) {
            return;
        }

        // Streaming is handled by FunctionController, not the normal flow
        // This event allows other extensions to process before streaming starts
    }

    /**
     * Convert extension to capabilities metadata for discovery responses.
     *
     * Provides SSE streaming capabilities including supported event types
     * (data, progress, error, done) and content-type information for clients.
     *
     * @return array<string, mixed> Streaming capabilities metadata
     */
    #[Override()]
    protected function getCapabilityMetadata(): array
    {
        return [
            'content_type' => 'text/event-stream',
            'events' => ['data', 'progress', 'error', 'done'],
        ];
    }
}

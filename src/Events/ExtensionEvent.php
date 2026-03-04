<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Events;

use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Data\ResponseData;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Abstract base class for Forrst extension lifecycle events.
 *
 * Provides infrastructure for extension events throughout the request processing
 * lifecycle. Events are mutable objects that allow extensions to inspect, modify,
 * and control request execution flow. Extensions can short-circuit processing by
 * stopping propagation and providing an immediate response.
 *
 * Events support three primary capabilities:
 * - Accessing immutable request data for inspection and decision-making
 * - Stopping event propagation to prevent subsequent listeners from executing
 * - Setting a short-circuit response to bypass normal execution and return immediately
 *
 * Concrete event classes extend this base to provide lifecycle-specific context
 * such as pre-execution (ExecutingFunction), post-execution (FunctionExecuted),
 * and validation (RequestValidated) events.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/protocol
 */
abstract class ExtensionEvent
{
    use Dispatchable;

    /**
     * Indicates whether event propagation has been stopped.
     *
     * When true, the event dispatcher will not invoke any remaining listeners
     * for this event. Used to short-circuit processing when an extension has
     * handled the event and wants to prevent further processing.
     */
    private bool $propagationStopped = false;

    /**
     * Response to return immediately, bypassing normal execution.
     *
     * When set in combination with stopped propagation, this response will be
     * returned to the client instead of executing the requested function. Used
     * by caching extensions to return cached responses or by idempotency
     * extensions to return previously computed results.
     */
    private ?ResponseData $shortCircuitResponse = null;

    /**
     * Create a new extension event instance.
     *
     * @param RequestObjectData $request The validated request object being processed.
     *                                   Provides extensions with access to function name,
     *                                   arguments, protocol version, extension options,
     *                                   and request metadata for decision-making.
     */
    public function __construct(
        public readonly RequestObjectData $request,
    ) {}

    /**
     * Stop further event propagation to subsequent listeners.
     *
     * Prevents remaining event listeners from being invoked for this event.
     * Typically called in combination with setResponse() to short-circuit
     * request processing and return an immediate response without executing
     * the target function. Once called, isPropagationStopped() returns true.
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * Check whether event propagation has been stopped.
     *
     * Returns true if any listener has called stopPropagation() on this event.
     * The event dispatcher uses this to determine whether to continue invoking
     * listeners or to halt processing and check for a short-circuit response.
     *
     * @return bool True if propagation was stopped, false otherwise
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Set a response to return immediately, bypassing function execution.
     *
     * Provides a response that will be returned to the client instead of
     * executing the requested function. Must be used with stopPropagation()
     * to prevent subsequent listeners from modifying the response. Common
     * for cache hits, idempotent request replay, and circuit breaker patterns.
     *
     * @param ResponseData $response The response to return to the client, containing
     *                               result data, metadata, and any error information
     */
    public function setResponse(ResponseData $response): void
    {
        $this->shortCircuitResponse = $response;
    }

    /**
     * Retrieve the short-circuit response if one was set.
     *
     * Returns the response set by a listener to bypass normal function execution.
     * Returns null if no short-circuit response was provided. The dispatcher
     * checks this after propagation is stopped to determine if an immediate
     * response should be returned.
     *
     * @return null|ResponseData The short-circuit response or null if not set
     */
    public function getResponse(): ?ResponseData
    {
        return $this->shortCircuitResponse;
    }

    /**
     * Check if a short-circuit response is set.
     *
     * Returns true if setResponse() has been called with a non-null response.
     * Useful for determining if the event has been fully handled.
     *
     * @return bool True if response is set, false otherwise
     */
    public function hasResponse(): bool
    {
        return $this->shortCircuitResponse instanceof ResponseData;
    }

    /**
     * Check if the event is in a valid short-circuit state.
     *
     * A valid short-circuit requires both propagation stopped AND a response set.
     * This ensures the dispatcher knows exactly what to do with a stopped event.
     *
     * @return bool True if event is properly short-circuited
     */
    public function isShortCircuited(): bool
    {
        return $this->propagationStopped && $this->shortCircuitResponse instanceof ResponseData;
    }

    /**
     * Stop propagation and set response atomically.
     *
     * Convenience method that ensures propagation is stopped and response is set
     * together, preventing inconsistent state. This is the recommended way to
     * short-circuit execution.
     *
     * @param ResponseData $response The response to return to the client
     */
    public function shortCircuit(ResponseData $response): void
    {
        $this->shortCircuitResponse = $response;
        $this->propagationStopped = true;
    }
}

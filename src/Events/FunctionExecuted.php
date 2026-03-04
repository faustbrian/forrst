<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Events;

use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Data\ResponseData;
use Override;

/**
 * Event dispatched after successful function execution.
 *
 * Fired immediately after the target function has executed and returned a
 * response, before the response is serialized and sent to the client. This
 * event allows extensions to inspect and modify the response, add metadata,
 * store results for caching or idempotency, or perform post-execution tasks.
 *
 * Common use cases include cache storage, idempotency result recording,
 * response transformation, deprecation warning injection, and telemetry
 * collection. Unlike ExecutingFunction, this event cannot short-circuit
 * execution since the function has already completed.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/protocol
 */
final class FunctionExecuted extends ExtensionEvent
{
    /**
     * The current response (mutable via setter).
     */
    private ResponseData $currentResponse;

    /**
     * Create a new function executed event instance.
     *
     * @param RequestObjectData $request   The original validated request that was executed,
     *                                     containing function name, arguments, and metadata.
     *                                     Useful for extensions that need to correlate the
     *                                     request with the response for caching or logging.
     * @param ExtensionData     $extension Extension-specific options from the request that
     *                                     guided execution behavior. Contains cache directives,
     *                                     idempotency keys, replay settings, and other extension
     *                                     configuration that may affect post-execution handling.
     * @param ResponseData      $response  The initial function response. Extensions can retrieve
     *                                     the current response via getResponse() and set a new
     *                                     response via setResponse() without mutating this value.
     */
    public function __construct(
        RequestObjectData $request,
        public readonly ExtensionData $extension,
        public readonly ResponseData $response,
    ) {
        parent::__construct($request);
        $this->currentResponse = $response;
    }

    /**
     * Get the current response (may differ from initial if modified).
     */
    #[Override()]
    public function getResponse(): ResponseData
    {
        return $this->currentResponse;
    }

    /**
     * Set a new response without mutating the event's readonly properties.
     */
    #[Override()]
    public function setResponse(ResponseData $response): void
    {
        $this->currentResponse = $response;
    }
}

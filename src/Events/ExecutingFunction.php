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

/**
 * Event dispatched immediately before function execution begins.
 *
 * Fired after request validation and routing but before the target function
 * is invoked. This represents the last opportunity for extensions to intercept
 * and short-circuit execution by returning a cached or pre-computed response.
 *
 * Common use cases include cache lookups, idempotency checks, circuit breakers,
 * and request transformation. Extensions can call stopPropagation() and
 * setResponse() to bypass function execution entirely.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/protocol
 */
final class ExecutingFunction extends ExtensionEvent
{
    /**
     * Create a new executing function event instance.
     *
     * @param RequestObjectData $request   The validated request object containing function name,
     *                                     arguments, protocol version, and metadata. This is the
     *                                     fully parsed request that will be executed unless an
     *                                     extension short-circuits execution.
     * @param ExtensionData     $extension Extension-specific data and options from the request.
     *                                     Contains configuration for extensions like caching,
     *                                     idempotency, replay, and custom extension parameters
     *                                     that control request processing behavior.
     */
    public function __construct(
        RequestObjectData $request,
        public readonly ExtensionData $extension,
    ) {
        parent::__construct($request);
    }
}

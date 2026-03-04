<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Contracts;

/**
 * Forrst unwrapped response marker interface.
 *
 * Defines a marker interface for response objects that should bypass the default
 * DocumentData envelope wrapping. Responses implementing this interface are returned
 * directly without structural transformation, preserving their raw format.
 *
 * Implementing this interface signals that a response object should be
 * returned directly without wrapping in a DocumentData envelope. This is
 * useful for functions that need to return raw data structures or when the
 * response already conforms to a specific format requirement.
 *
 * By default, all RPC responses are wrapped in a DocumentData structure
 * following JSON:API conventions. This interface provides an escape hatch
 * for responses that need to maintain their raw structure for compatibility
 * or performance reasons.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/protocol Protocol specification
 * @see https://docs.cline.sh/forrst/resource-objects Resource objects
 */
interface UnwrappedResponseInterface
{
    // Marker interface with no methods - used for type detection only
}

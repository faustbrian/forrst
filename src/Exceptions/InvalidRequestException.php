<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

/**
 * Base exception for Forrst request that is malformed or structurally invalid.
 *
 * Represents Forrst error code INVALID_REQUEST for requests that fail basic structural
 * validation, such as missing required members (jsonrpc, method, id), invalid
 * Forrst version, or malformed request structure. This is distinct from parameter
 * validation errors which use error code INVALID_ARGUMENTS.
 *
 * This abstract class serves as the foundation for specific request validation
 * exceptions. Concrete implementations should handle particular types of structural
 * validation failures.
 *
 * Results in HTTP 400 status indicating client error in request format.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 * @see https://docs.cline.sh/forrst/protocol Protocol specification
 */
abstract class InvalidRequestException extends AbstractRequestException
{
    // Abstract base - no factory methods
}

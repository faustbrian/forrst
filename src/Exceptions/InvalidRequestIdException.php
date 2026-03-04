<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use InvalidArgumentException;

/**
 * Exception thrown when RPC request ID is missing or has an invalid type.
 *
 * The request ID is a critical component of Forrst RPC protocol that enables
 * correlation between client requests and server responses. This exception
 * is raised during request parsing when the ID field is absent or not a
 * string value, preventing proper request-response matching.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/protocol
 * @see https://docs.cline.sh/forrst/errors
 */
final class InvalidRequestIdException extends InvalidArgumentException implements RpcException
{
    /**
     * Creates an exception for a missing or invalid request ID.
     *
     * @return self a new exception instance with a descriptive error message
     */
    public static function create(): self
    {
        return new self('Request ID is required and must be a string');
    }
}

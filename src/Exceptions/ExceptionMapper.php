<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Maps Laravel exceptions to Forrst exception types.
 *
 * Part of the Forrst protocol exception mapping. Provides centralized exception
 * mapping logic that transforms standard Laravel and PHP exceptions into appropriate
 * Forrst exception instances with proper error codes and messages. Ensures consistent
 * error handling across the Forrst server implementation.
 *
 * The mapper acts as a translation layer between Laravel's exception system and the
 * Forrst protocol error specification, allowing standard Laravel exceptions to be
 * automatically converted into properly formatted Forrst error responses without
 * requiring changes to application code.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/errors
 */
final class ExceptionMapper
{
    /**
     * Map an exception to a Forrst exception.
     *
     * Transforms standard Laravel and PHP exceptions into Forrst compliant exception
     * instances with appropriate error codes and messages. The mapping follows
     * these rules:
     *
     * - AbstractRequestException: Passed through unchanged (already Forrst compliant)
     * - AuthenticationException: Mapped to UnauthorizedException (401 Unauthorized)
     * - AuthorizationException: Mapped to ForbiddenException (403 Forbidden)
     * - ItemNotFoundException/ModelNotFoundException: Mapped to ResourceNotFoundException (404 Not Found)
     * - ThrottleRequestsException: Mapped to TooManyRequestsException (429 Too Many Requests)
     * - ValidationException: Mapped to ParameterValidationException with validation details (422 Unprocessable Entity)
     * - All other exceptions: Mapped to InternalErrorException (500 Internal Server Error)
     *
     * @param  Throwable                $exception The exception to map to a Forrst exception type.
     *                                             Can be any Laravel exception, PHP exception, or
     *                                             existing Forrst exception instance.
     * @return AbstractRequestException The mapped Forrst exception with appropriate error code,
     *                                  message, and HTTP status code
     */
    public static function execute(Throwable $exception): AbstractRequestException
    {
        return match (true) {
            $exception instanceof AbstractRequestException => $exception,
            $exception instanceof AuthenticationException => UnauthorizedException::create(),
            $exception instanceof AuthorizationException => ForbiddenException::create(),
            $exception instanceof ItemNotFoundException => ResourceNotFoundException::create(),
            $exception instanceof ModelNotFoundException => ResourceNotFoundException::create(),
            $exception instanceof ThrottleRequestsException => TooManyRequestsException::create(),
            $exception instanceof ValidationException => ParameterValidationException::fromValidationException($exception),
            default => InternalErrorException::create($exception),
        };
    }
}

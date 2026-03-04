<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Cline\Forrst\Enums\ErrorCode;

/**
 * Exception thrown when an unexpected server error occurs.
 *
 * Represents generic server-side failures that don't fit into more specific error
 * categories. This exception uses the InternalError error code and maps to HTTP 500
 * (Internal Server Error). Use this for unexpected failures, infrastructure issues,
 * unhandled errors, or any internal server problem that prevents request processing.
 *
 * This is the catch-all exception for server errors that are not maintenance-related,
 * rate-limited, authentication failures, or validation errors. It indicates something
 * went wrong on the server side that was not anticipated by the application logic.
 *
 * ```php
 * try {
 *     $result = $service->performComplexOperation();
 * } catch (\Throwable $e) {
 *     throw ServerErrorException::create([
 *         'detail' => 'Failed to process operation: ' . $e->getMessage(),
 *         'source' => ['pointer' => '/data/attributes/operation']
 *     ]);
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 */
final class ServerErrorException extends AbstractRequestException
{
    /**
     * Creates a server error exception with optional error details.
     *
     * Generates a Forrst-compliant error response for unexpected server failures
     * with HTTP 500 status code. The exception uses the InternalError code to
     * indicate a generic server-side failure that prevented request processing.
     *
     * @param  null|array<int|string, mixed> $data Additional error data formatted according to
     *                                             JSON:API error object specifications. Can include
     *                                             fields like 'detail', 'source', 'meta', or custom
     *                                             diagnostic information about the server failure.
     *                                             When null, only the generic error code and message
     *                                             are included in the response.
     * @return self                          The created exception instance with error code InternalError,
     *                                       default message "Server error", and the provided error details
     *                                       formatted as a JSON:API error response.
     */
    public static function create(?array $data = null): self
    {
        // @phpstan-ignore-next-line argument.type
        return self::new(ErrorCode::InternalError, 'Server error', details: $data);
    }
}

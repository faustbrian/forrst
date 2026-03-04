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
 * Exception thrown when a requested Forrst server cannot be found.
 *
 * Represents routing failures where the requested server identifier or route name
 * does not match any configured Forrst server endpoint in the registry. This exception
 * uses the Unavailable error code and typically occurs during request routing when
 * the system cannot locate the target server for processing the RPC request.
 *
 * This is distinct from general service unavailability - it specifically indicates
 * that the server configuration is missing or the route does not exist. Use this when
 * a client requests a server that is not registered in the application's Forrst
 * configuration, resulting in an inability to route the request.
 *
 * ```php
 * $server = $this->serverRegistry->find($routeName);
 *
 * if ($server === null) {
 *     throw ServerNotFoundException::create([
 *         'detail' => "Server not found for route: {$routeName}",
 *         'source' => ['parameter' => 'route']
 *     ]);
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 * @see https://docs.cline.sh/forrst/protocol Protocol specification
 */
final class ServerNotFoundException extends AbstractRequestException
{
    /**
     * Creates a server not found exception with optional error details.
     *
     * Generates a Forrst-compliant error response indicating the requested server
     * could not be found in the registry. Uses the Unavailable error code to signal
     * that the server endpoint does not exist or is not configured.
     *
     * @param  null|array<int|string, mixed> $data Additional error data formatted according to
     *                                             JSON:API error object specifications. Typically
     *                                             includes 'detail' explaining which server was not
     *                                             found and 'source' pointing to the route parameter
     *                                             or identifier that failed to resolve. When null,
     *                                             only the generic error code and message are included.
     * @return self                          The created exception instance with error code Unavailable,
     *                                       default message "Service unavailable", and the provided error
     *                                       details for debugging server routing failures.
     */
    public static function create(?array $data = null): self
    {
        // @phpstan-ignore-next-line argument.type
        return self::new(ErrorCode::Unavailable, 'Service unavailable', details: $data);
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Cline\Forrst\Enums\ErrorCode;
use Override;

/**
 * Exception thrown when the service is temporarily unavailable.
 *
 * Represents temporary service disruptions due to overload, transient failures,
 * or unplanned outages. This exception uses the Unavailable error code and maps
 * to HTTP 503 (Service Unavailable), signaling that clients should retry the
 * request after a delay.
 *
 * Use this exception for temporary conditions like database connection failures,
 * resource exhaustion, or transient infrastructure issues. Unlike ServerMaintenanceException
 * which represents planned downtime, this indicates unexpected temporary unavailability
 * without a known end time or structured retry timing.
 *
 * ```php
 * try {
 *     $db->connect();
 * } catch (ConnectionException $e) {
 *     throw ServiceUnavailableException::create(
 *         'Database connection pool exhausted'
 *     );
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 */
final class ServiceUnavailableException extends AbstractRequestException
{
    /**
     * Creates a service unavailable exception with optional error details.
     *
     * Generates a Forrst-compliant error response for temporary service disruptions
     * with HTTP 503 status code. The exception uses the Unavailable error code to
     * indicate the server is temporarily unable to process requests.
     *
     * @param  null|string $detail Detailed explanation of why the service is unavailable,
     *                             such as "Database connection pool exhausted" or "Redis
     *                             cache unavailable". When null, a generic message about
     *                             temporary overload or maintenance is used. This detail
     *                             appears in the JSON:API error response to help clients
     *                             understand the nature of the unavailability.
     * @return self        The created exception instance with error code Unavailable,
     *                     default message "Service unavailable", and HTTP 503 status,
     *                     formatted according to JSON:API error object specifications
     *                     with status, title, and detail fields.
     */
    public static function create(?string $detail = null): self
    {
        // @phpstan-ignore-next-line argument.type
        return self::new(ErrorCode::Unavailable, 'Service unavailable', details: [
            [
                'status' => '503',
                'title' => 'Service Unavailable',
                'detail' => $detail ?? 'The server is currently unable to handle the request due to a temporary overload or scheduled maintenance, which will likely be alleviated after some delay.',
            ],
        ]);
    }

    /**
     * Gets the HTTP status code for this exception.
     *
     * Returns HTTP 503 (Service Unavailable) to indicate the server is temporarily
     * unable to handle the request and clients should retry after a delay.
     *
     * @return int HTTP 503 Service Unavailable status code
     */
    #[Override()]
    public function getStatusCode(): int
    {
        return 503;
    }
}

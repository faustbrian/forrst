<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Data;

/**
 * Complete result of a Forrst request execution including HTTP metadata.
 *
 * Encapsulates the full response including the Forrst protocol response data,
 * HTTP status code, and response headers. This is the final output container
 * used by the server to construct the HTTP response sent back to the client.
 * Combines protocol-level response data with transport-level HTTP metadata.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/protocol
 * @see https://docs.cline.sh/forrst/document-structure
 */
final class RequestResultData extends AbstractData
{
    /**
     * Create a new request result instance.
     *
     * @param mixed                 $data       The response payload containing either a successful
     *                                          result (ResponseData with result) or an error response
     *                                          (ResponseData with error).
     * @param int                   $statusCode HTTP status code for the response. Uses semantic HTTP status
     *                                          codes: 200 for success, 400 for invalid arguments, 401 for
     *                                          unauthorized, 403 for forbidden, 404 for not found, 422 for
     *                                          schema validation failures, 429 for rate limited, 500 for
     *                                          internal errors, 503 for unavailable, 504 for deadline exceeded.
     * @param array<string, string> $headers    Optional HTTP response headers to include
     *                                          in the response. May contain content-type,
     *                                          cache control, CORS headers, or custom
     *                                          application-specific headers.
     */
    public function __construct(
        public readonly mixed $data,
        public readonly int $statusCode,
        public readonly array $headers = [],
    ) {}
}

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
 * Exception thrown when a request fails Forrst structural validation.
 *
 * Represents violations of the basic Forrst protocol structure, such as missing
 * required members, malformed request objects, or incorrect data types at the
 * protocol level. This exception uses the InvalidRequest error code and is distinct
 * from semantic validation which checks business rules on well-formed requests.
 *
 * Use this exception when the request does not conform to the Forrst protocol
 * specification itself, before any method-specific or business logic validation
 * occurs. Common cases include missing 'method' field, invalid 'params' structure,
 * or protocol version mismatches.
 *
 * ```php
 * if (!isset($request->method)) {
 *     throw StructurallyInvalidRequestException::create([
 *         [
 *             'status' => '400',
 *             'source' => ['pointer' => '/method'],
 *             'title' => 'Missing Required Field',
 *             'detail' => 'The request object must include a method field'
 *         ]
 *     ]);
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 * @see https://docs.cline.sh/forrst/protocol Protocol specification
 */
final class StructurallyInvalidRequestException extends InvalidRequestException
{
    /**
     * Creates a structural validation exception with optional error details.
     *
     * Generates a Forrst-compliant error response for protocol-level structural
     * validation failures. The exception uses the InvalidRequest error code to
     * indicate the request does not meet basic Forrst specification requirements.
     *
     * @param  null|array<int, array<string, mixed>> $data Array of JSON:API error objects
     *                                                     describing specific structural violations.
     *                                                     Each error should include 'status', 'source'
     *                                                     with a JSON Pointer, 'title', and 'detail'
     *                                                     fields to precisely identify what structural
     *                                                     requirement was violated and where. When null,
     *                                                     only the generic error code and message are
     *                                                     included for basic structural failures.
     * @return self                                  The created exception instance with error code InvalidRequest,
     *                                               default message "Invalid request", and the provided structural
     *                                               error details formatted according to JSON:API error specifications.
     */
    public static function create(?array $data = null): self
    {
        // @phpstan-ignore-next-line argument.type
        return self::new(ErrorCode::InvalidRequest, 'Invalid request', details: $data);
    }
}

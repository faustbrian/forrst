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
 * Exception thrown when a request fails semantic validation.
 *
 * Represents validation failures where the request structure is well-formed and
 * meets Forrst protocol requirements, but the data violates business rules, constraints,
 * or logical requirements. This exception maps to HTTP 422 (Unprocessable Entity)
 * and uses the SchemaValidationFailed error code.
 *
 * Use this exception when the request is structurally valid but contains data that
 * cannot be processed due to semantic errors such as invalid email formats, out-of-range
 * values, or business rule violations. The exception provides JSON:API-compliant
 * error responses with detailed explanations of what went wrong.
 *
 * ```php
 * // Validate email format
 * if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
 *     throw SemanticValidationException::create('Invalid email format');
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 */
final class SemanticValidationException extends UnprocessableEntityException
{
    /**
     * Creates a semantic validation exception with optional error details.
     *
     * Generates a Forrst-compliant error response for semantic validation failures
     * with HTTP 422 status code. The exception uses the SchemaValidationFailed
     * error code to indicate that while the request structure is valid, the data
     * violates semantic constraints or business rules.
     *
     * @param  null|string $detail Detailed explanation of the validation failure, such as
     *                             "Email format is invalid" or "Price must be positive".
     *                             When null, a generic message about semantic errors is used.
     *                             This detail appears in the JSON:API error response to help
     *                             clients understand and fix the validation issue.
     * @return self        The created exception instance with error code SchemaValidationFailed
     *                     and HTTP 422 status, formatted according to JSON:API error object
     *                     specifications with status, title, and detail fields.
     */
    public static function create(?string $detail = null): self
    {
        // @phpstan-ignore-next-line argument.type
        return self::new(ErrorCode::SchemaValidationFailed, 'Validation error', details: [
            [
                'status' => '422',
                'title' => 'Unprocessable Entity',
                'detail' => $detail ?? 'The request was well-formed but was unable to be followed due to semantic errors.',
            ],
        ]);
    }
}

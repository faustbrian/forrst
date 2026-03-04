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
 * Exception for forbidden access errors (HTTP 403).
 *
 * Part of the Forrst protocol HTTP error exceptions. Represents authorization failures
 * where the client is authenticated but lacks permission to access the requested
 * resource or perform the requested action. Maps to HTTP 403 Forbidden status and
 * Forrst FORBIDDEN error code.
 *
 * This exception is thrown when the user's identity is known (authenticated) but
 * they do not have the necessary permissions to perform the requested operation.
 * Common use cases include role-based access control failures, resource ownership
 * checks, and permission guard violations.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/errors
 */
final class ForbiddenException extends AbstractRequestException
{
    /**
     * Create a new forbidden exception instance.
     *
     * Factory method that constructs a forbidden exception with a standardized error
     * structure containing status code, title, and detail message. Used when a user
     * is authenticated but lacks the necessary permissions for the requested operation.
     *
     * The error details follow JSON:API error object specification format, where errors
     * are represented as an array of error objects. This differs from non-HTTP exceptions
     * to maintain JSON:API compatibility for HTTP status code exceptions.
     *
     * SECURITY WARNING: The detail message is returned to clients. Do not include
     * sensitive information such as:
     * - Internal role/permission names or IDs
     * - Database identifiers or internal keys
     * - Authorization logic implementation details
     * - User data from authorization checks
     * - Specific security mechanism information
     *
     * Keep messages user-friendly and generic. Good examples:
     * - "You do not have permission to delete this resource"
     * - "Insufficient permissions to perform this action"
     * - "Access to this feature requires additional permissions"
     *
     * @see https://jsonapi.org/format/#error-objects
     *
     * @param  null|string $detail Optional detailed error message explaining why access
     *                             was denied. Defaults to a generic authorization failure
     *                             message if not provided. Use custom messages to provide
     *                             specific feedback like "You do not have permission to
     *                             delete this resource."
     * @return self        The constructed forbidden exception instance
     */
    public static function create(?string $detail = null): self
    {
        // JSON:API error format uses array of error objects (array<int, array<string, string>>)
        // PHPStan expects array<string, mixed> for the details parameter, hence the suppression
        // @phpstan-ignore-next-line argument.type
        return self::new(ErrorCode::Forbidden, 'Forbidden', details: [
            [
                'status' => '403',
                'title' => 'Forbidden',
                'detail' => $detail ?? 'You are not authorized to perform this action.',
            ],
        ]);
    }

    /**
     * Get the HTTP status code for this exception.
     *
     * @return int Always returns 403 (Forbidden) to indicate authorization failure
     */
    #[Override()]
    public function getStatusCode(): int
    {
        return 403;
    }
}

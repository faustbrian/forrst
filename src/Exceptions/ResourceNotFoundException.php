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
 * Exception thrown when a requested resource does not exist.
 *
 * Represents a Forrst server error that maps to HTTP 404, indicating the requested
 * resource (model, entity, or database record) could not be found. This is typically
 * used within RPC method implementations when looking up resources by ID or other
 * identifiers. The exception wraps the error in JSON:API format with Forrst error
 * code INTERNAL_ERROR, providing structured error information to the client while
 * maintaining protocol compliance.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/resource-objects Resource objects specification
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 */
final class ResourceNotFoundException extends NotFoundException
{
    /**
     * Create a resource not found exception.
     *
     * @param null|string $detail Detailed explanation of which resource was not found
     *                            and why. Should provide context for debugging, such as
     *                            the resource type and identifier that was requested.
     *                            If null, uses a generic default message. Included in
     *                            the JSON:API error response detail field.
     *
     * @return self Forrst exception with INTERNAL_ERROR code, HTTP 404 status, and JSON:API
     *              formatted error including the provided detail message
     */
    public static function create(?string $detail = null): self
    {
        // @phpstan-ignore-next-line argument.type
        return self::new(ErrorCode::InternalError, 'Server error', details: [
            [
                'status' => '404',
                'title' => 'Resource Not Found',
                'detail' => $detail ?? 'The requested model could not be found.',
            ],
        ]);
    }
}

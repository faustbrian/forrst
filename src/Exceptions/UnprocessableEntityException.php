<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Override;

/**
 * Base exception for semantic validation failures.
 *
 * Serves as the abstract base class for all exceptions representing validation
 * failures where requests are structurally valid but contain data that violates
 * business rules or constraints. This exception maps to HTTP 422 (Unprocessable
 * Entity) and provides JSON:API-compliant error formatting with source pointers
 * for precise error location.
 *
 * Concrete implementations like SemanticValidationException extend this base class
 * to provide specific validation failure scenarios. The exception hierarchy separates
 * structural validation (protocol violations) from semantic validation (business rule
 * violations) to enable fine-grained error handling.
 *
 * All validation errors use JSON:API error object format with 'source' pointers
 * indicating the exact location of the invalid data in the request document, making
 * it easy for clients to identify and correct validation failures.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 */
abstract class UnprocessableEntityException extends AbstractRequestException
{
    /**
     * Gets the HTTP status code for validation failure exceptions.
     *
     * Returns HTTP 422 (Unprocessable Entity) to indicate the request is well-formed
     * but contains semantic errors that prevent processing. This status code signals
     * to clients that they need to fix the request data to meet validation constraints.
     *
     * @return int HTTP 422 Unprocessable Entity status code
     */
    #[Override()]
    public function getStatusCode(): int
    {
        return 422;
    }
}

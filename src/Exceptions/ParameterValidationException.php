<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Cline\Forrst\Enums\ErrorCode;
use Illuminate\Validation\ValidationException;

/**
 * Exception thrown when method parameter validation fails.
 *
 * Converts Laravel's ValidationException into a Forrst-compliant error response with
 * JSON:API formatted error objects. Each validation failure is transformed into a
 * separate error object with a source pointer (e.g., /params/email) indicating the
 * specific parameter field that failed validation, enabling precise client-side
 * error handling and user feedback.
 *
 * This exception is specifically for method parameter validation failures, as opposed
 * to RequestValidationFailedException which handles top-level Forrst request structure
 * violations. Uses HTTP 422 status and Forrst error code SCHEMA_VALIDATION_FAILED.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 * @see https://docs.cline.sh/forrst/protocol Protocol specification
 */
final class ParameterValidationException extends UnprocessableEntityException
{
    /**
     * Create a Forrst exception from a Laravel validation exception.
     *
     * Transforms Laravel's ValidationException into a Forrst-compliant error response
     * by flattening all validation errors into individual JSON:API error objects.
     * Each error includes HTTP 422 status, a JSON Pointer source indicating the
     * specific parameter field (e.g., /params/email), and the validation failure
     * message from Laravel's validator.
     *
     * @param ValidationException $exception Laravel validation exception containing error
     *                                       messages organized by field name. Each field can
     *                                       have multiple error messages that are flattened
     *                                       into separate JSON:API error objects for detailed
     *                                       client-side error handling and field-level feedback.
     *
     * @return self Forrst exception instance with SCHEMA_VALIDATION_FAILED error code and
     *              normalized validation errors formatted as JSON:API error objects
     */
    public static function fromValidationException(ValidationException $exception): self
    {
        $normalized = [];

        foreach ($exception->errors() as $attribute => $errors) {
            // @phpstan-ignore-next-line foreach.nonIterable
            foreach ($errors as $error) {
                $normalized[] = [
                    'status' => '422',
                    'source' => ['pointer' => '/params/'.$attribute],
                    'title' => 'Invalid params',
                    'detail' => $error,
                ];
            }
        }

        // @phpstan-ignore-next-line argument.type
        return self::new(ErrorCode::SchemaValidationFailed, 'Validation error', details: $normalized);
    }
}

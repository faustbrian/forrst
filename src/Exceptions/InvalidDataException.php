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
 * Exception thrown when request data fails validation rules.
 *
 * Represents Forrst error code INVALID_ARGUMENTS, specifically for validation failures in the
 * data payload of RPC requests. Transforms Laravel validation errors into Forrst
 * compliant error responses with detailed field-level error information and JSON
 * Pointer references for precise error location tracking.
 *
 * Results in HTTP 422 status with detailed validation errors for each failed field.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 * @see https://docs.cline.sh/forrst/protocol Protocol specification
 */
final class InvalidDataException extends AbstractRequestException
{
    /**
     * Creates an invalid data exception from a Laravel validation exception.
     *
     * Transforms Laravel validation errors into a Forrst compliant error response
     * format. Each validation error is converted into a separate error object with
     * JSON Pointer notation indicating the exact field location and HTTP 422 status.
     *
     * @param ValidationException $exception Laravel validation exception containing field-level
     *                                       validation errors with attribute names and error
     *                                       messages. The errors are normalized into Forrst
     *                                       error format with pointer references to specific
     *                                       fields in the request data payload.
     *
     * @return self A new instance containing all validation errors formatted as Forrst
     *              error objects, each with HTTP 422 status, JSON Pointer source location
     *              (/params/data/{attribute}), and the specific validation message.
     */
    public static function create(ValidationException $exception): self
    {
        $normalized = [];

        foreach ($exception->errors() as $attribute => $errors) {
            // @phpstan-ignore-next-line foreach.nonIterable
            foreach ($errors as $error) {
                $normalized[] = [
                    'status' => '422',
                    'source' => ['pointer' => '/params/data/'.$attribute],
                    'title' => 'Invalid params',
                    'detail' => $error,
                ];
            }
        }

        // @phpstan-ignore-next-line argument.type
        return self::new(ErrorCode::InvalidArguments, 'Invalid arguments', details: $normalized);
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Cline\Forrst\Enums\ErrorCode;
use Illuminate\Validation\Validator;

/**
 * Exception thrown when Forrst request structure validation fails.
 *
 * Transforms Laravel validation errors for top-level Forrst request structure into
 * a Forrst-compliant error response. This exception handles structural violations
 * like missing or invalid 'jsonrpc', 'method', 'id', or 'params' members, as
 * opposed to ParameterValidationException which handles method parameter validation.
 * Each error is converted into a JSON:API error object with JSON Pointer notation
 * indicating the exact request member that violated Forrst protocol requirements.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 * @see https://docs.cline.sh/forrst/protocol Protocol specification
 */
final class RequestValidationFailedException extends InvalidRequestException
{
    /**
     * Create a Forrst exception from a Laravel validator instance.
     *
     * Transforms Laravel validation errors for top-level Forrst request structure into
     * individual JSON:API error objects. Each error includes HTTP 422 status, a JSON
     * Pointer source indicating the specific request member (e.g., /jsonrpc, /method),
     * and the validation failure message. This is used for protocol-level validation
     * failures, not method parameter validation.
     *
     * @param Validator $validator Laravel validator instance containing structural
     *                             validation errors for the Forrst request. Typically
     *                             validates required top-level members like 'jsonrpc',
     *                             'method', 'id', and 'params' according to Forrst protocol
     *                             specification, ensuring request format compliance before
     *                             method execution.
     *
     * @return self Forrst exception instance with INVALID_REQUEST error code and normalized
     *              validation errors formatted as JSON:API error objects
     */
    public static function fromValidator(Validator $validator): self
    {
        $normalized = [];

        foreach ($validator->errors()->messages() as $attribute => $errors) {
            foreach ($errors as $error) {
                $normalized[] = [
                    'status' => '422',
                    'source' => ['pointer' => '/'.$attribute],
                    'title' => 'Invalid member',
                    'detail' => $error,
                ];
            }
        }

        // @phpstan-ignore-next-line argument.type
        return self::new(ErrorCode::InvalidRequest, 'Invalid request', details: $normalized);
    }
}

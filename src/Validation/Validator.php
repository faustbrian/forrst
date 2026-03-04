<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Validation;

use Illuminate\Validation\ValidationException;

use function throw_if;
use function validator;

/**
 * Validation utility for RPC request data.
 *
 * Provides a simplified static interface for validating request parameters using
 * Laravel's validation system. Throws validation exceptions on failure for centralized
 * error handling in the Forrst request pipeline, ensuring consistent error responses
 * across all RPC functions.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/protocol
 */
final class Validator
{
    /**
     * Validate data against the given validation rules.
     *
     * Creates a Laravel validator instance and validates the provided data against
     * the specified rules. Throws a ValidationException with all error messages if
     * validation fails, which is then caught and formatted by the RPC error handler
     * into a standardized error response.
     *
     * ```php
     * Validator::validate($request->params, [
     *     'email' => 'required|email',
     *     'age' => 'required|integer|min:18',
     * ]);
     * ```
     *
     * @param array<string, mixed>                     $data  Data to validate, typically RPC function parameters
     *                                                        from the request object
     * @param array<string, array<int, string>|string> $rules Laravel validation rules defining constraints for
     *                                                        each parameter. Supports all standard Laravel
     *                                                        validation rules and custom rule objects.
     *
     * @throws ValidationException When validation fails, containing all validation error messages
     *                             formatted for RPC error response
     */
    public static function validate(array $data, array $rules): void
    {
        $validator = validator($data, $rules);

        throw_if($validator->fails(), ValidationException::withMessages($validator->errors()->all()));
    }
}

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
 * Exception thrown when method parameters are invalid or malformed.
 *
 * Represents Forrst error code INVALID_ARGUMENTS for general parameter validation failures.
 * This is a generic parameter exception used when request parameters do not meet
 * method requirements but are not covered by more specific validation exceptions
 * like InvalidDataException or InvalidFieldsException.
 *
 * Results in HTTP 422 status with optional detailed error information.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 * @see https://docs.cline.sh/forrst/protocol Protocol specification
 */
final class InvalidParamsException extends AbstractRequestException
{
    /**
     * Creates an invalid params exception with optional error details.
     *
     * Generates a Forrst compliant error response for parameter validation failures.
     * This method is typically used for general parameter errors that don't require
     * the detailed field-level error tracking provided by more specific exceptions.
     *
     * @param null|array<int, array<string, mixed>> $data Optional array of error details
     *                                                    following JSON:API error object
     *                                                    structure. Each error should contain
     *                                                    status, source pointer, title, and
     *                                                    detail fields for client debugging.
     *
     * @return self A new instance with Forrst error code INVALID_ARGUMENTS, HTTP 422 status,
     *              and the provided error details, or null data for generic parameter
     *              validation failures without specific field information.
     */
    public static function create(?array $data = null): self
    {
        // @phpstan-ignore-next-line argument.type
        return self::new(ErrorCode::InvalidArguments, 'Invalid arguments', details: $data);
    }
}

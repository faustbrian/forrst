<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Cline\Forrst\Enums\ErrorCode;

use function array_diff;
use function implode;
use function sprintf;

/**
 * Exception thrown when requested fields are not permitted for sparse fieldset selection.
 *
 * Represents Forrst error code INVALID_ARGUMENTS for invalid field specifications in resource
 * queries. This exception is thrown when clients request fields that are not in the
 * allowed fieldset, preventing exposure of sensitive or unauthorized data through
 * sparse fieldset requests as per JSON:API conventions.
 *
 * Results in HTTP 422 status with details about which fields are invalid and which
 * fields are permitted.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 * @see https://docs.cline.sh/forrst/extensions/query Query extension
 */
final class InvalidFieldsException extends AbstractRequestException
{
    /**
     * Creates an invalid fields exception with detailed error information.
     *
     * Compares requested fields against the allowed fieldset and generates a
     * Forrst compliant error response detailing which fields are not permitted.
     * The error includes both unknown and allowed fields in the meta section for
     * client-side debugging and corrective action.
     *
     * @param array<int, string> $unknownFields Array of field names that were requested but
     *                                          are not in the allowed fieldset. These will
     *                                          be compared against allowed fields to identify
     *                                          the specific unauthorized field requests.
     * @param array<int, string> $allowedFields Array of field names that are permitted for
     *                                          sparse fieldset selection. Used to validate
     *                                          client requests and generate helpful error
     *                                          messages showing valid field options.
     *
     * @return self A new instance with HTTP 422 status, JSON Pointer to /params/fields,
     *              detailed error message comparing requested vs allowed fields, and
     *              meta information containing both unknown and allowed field lists.
     */
    public static function create(array $unknownFields, array $allowedFields): self
    {
        $unknownFields = implode(', ', array_diff($unknownFields, $allowedFields));
        $allowedFields = implode(', ', $allowedFields);

        // @phpstan-ignore-next-line argument.type
        return self::new(ErrorCode::InvalidArguments, 'Invalid arguments', details: [
            [
                'status' => '422',
                'source' => ['pointer' => '/params/fields'],
                'title' => 'Invalid fields',
                'detail' => sprintf('Requested fields `%s` are not allowed. Allowed fields are `%s`.', $unknownFields, $allowedFields),
                'meta' => [
                    'unknown' => $unknownFields,
                    'allowed' => $allowedFields,
                ],
            ],
        ]);
    }
}

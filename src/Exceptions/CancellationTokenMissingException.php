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
 * Exception thrown when a cancellation token is missing or empty.
 *
 * Part of the Forrst cancellation extension exceptions. Thrown when a cancel request
 * is received without a required cancellation token parameter, or when the token
 * parameter is present but empty. This indicates a malformed cancel request that
 * cannot be processed.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/cancellation
 */
final class CancellationTokenMissingException extends NotFoundException
{
    /**
     * Create an exception for a missing cancellation token.
     *
     * Factory method that constructs a cancellation token missing exception with
     * the CANCELLATION_TOKEN_UNKNOWN error code. Used when the cancel request
     * does not include the required token parameter.
     *
     * @param null|string $providedToken The token value that was provided, if any
     *
     * @return self The constructed exception instance
     */
    public static function create(?string $providedToken = null): self
    {
        if ($providedToken === '') {
            return self::new(
                code: ErrorCode::CancellationTokenUnknown,
                message: 'Cancellation token cannot be empty',
                details: ['error' => 'Token parameter was provided but contains an empty string'],
            );
        }

        return self::new(
            code: ErrorCode::CancellationTokenUnknown,
            message: 'Cancellation token is required',
            details: ['error' => 'Token parameter was not provided in the request'],
        );
    }
}

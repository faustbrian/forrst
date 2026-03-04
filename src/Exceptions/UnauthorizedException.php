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
 * Exception thrown when authentication fails or is not provided.
 *
 * Represents authentication failures where credentials are missing, invalid, or
 * expired. This exception uses the Unauthorized error code and maps to HTTP 401
 * (Unauthorized), indicating that valid authentication is required to access the
 * requested resource.
 *
 * Use this exception when the request lacks authentication credentials entirely,
 * provides invalid credentials (wrong token/password), or uses expired credentials.
 * Distinct from authorization failures (403 Forbidden) which occur when authenticated
 * users lack permission for the requested action.
 *
 * ```php
 * $token = $request->header('Authorization');
 *
 * if (!$token || !$this->validateToken($token)) {
 *     throw UnauthorizedException::create('Invalid or expired authentication token');
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 */
final class UnauthorizedException extends AbstractRequestException
{
    /**
     * Creates an authentication failure exception with optional error details.
     *
     * Generates a Forrst-compliant error response for authentication failures with
     * HTTP 401 status code. The exception uses the Unauthorized error code to signal
     * that valid authentication credentials are required to access the resource.
     *
     * @param  null|string $detail Detailed explanation of the authentication failure,
     *                             such as "Invalid API token provided", "Token has expired",
     *                             or "Missing authentication header". When null, a generic
     *                             message indicating the user is not authorized is used.
     *                             This detail appears in the JSON:API error response to help
     *                             clients diagnose and fix authentication issues.
     * @return self        The created exception instance with error code Unauthorized,
     *                     default message "Unauthorized", and HTTP 401 status, formatted
     *                     according to JSON:API error object specifications with status,
     *                     title, and detail fields.
     */
    public static function create(?string $detail = null): self
    {
        // @phpstan-ignore-next-line argument.type
        return self::new(ErrorCode::Unauthorized, 'Unauthorized', details: [
            [
                'status' => '401',
                'title' => 'Unauthorized',
                'detail' => $detail ?? 'You are not authorized to perform this action.',
            ],
        ]);
    }

    /**
     * Gets the HTTP status code for this exception.
     *
     * Returns HTTP 401 (Unauthorized) to indicate the request requires valid
     * authentication credentials. Clients should provide credentials and retry.
     *
     * @return int HTTP 401 Unauthorized status code
     */
    #[Override()]
    public function getStatusCode(): int
    {
        return 401;
    }
}

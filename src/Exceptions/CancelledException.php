<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Cline\Forrst\Enums\ErrorCode;

use function is_string;

/**
 * Exception thrown when a request has been cancelled by the client.
 *
 * Part of the Forrst cancellation extension exceptions. Represents the CANCELLED
 * error code for requests that were cancelled via the cancellation extension.
 * Functions should throw this exception when they detect that cancellation has
 * been requested for the current operation.
 *
 * The cancellation extension allows clients to explicitly cancel long-running
 * operations by sending a cancel request with the associated cancellation token.
 * When a function detects cancellation, it should stop processing and throw this
 * exception to return a proper CANCELLED error response to the client.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/cancellation
 */
final class CancelledException extends AbstractRequestException
{
    /**
     * Default cancellation reason message.
     */
    private const string DEFAULT_REASON = 'Request was cancelled by client';

    /**
     * Create a cancelled exception.
     *
     * Factory method that constructs a cancelled exception with optional token and
     * reason details. The token and reason are included in error details to provide
     * context about which request was cancelled and why.
     *
     * @param  null|string $token  The cancellation token that was used to cancel the request,
     *                             if available. Included in error details for correlation
     *                             with the original cancel request.
     * @param  null|string $reason Optional human-readable reason explaining why the request
     *                             was cancelled. Defaults to generic cancellation message
     *                             if not provided.
     * @return self        The constructed cancelled exception instance
     */
    public static function create(?string $token = null, ?string $reason = null): self
    {
        $details = [];

        if ($token !== null && $token !== '') {
            $details['token'] = $token;
        }

        if ($reason !== null && $reason !== '') {
            $details['reason'] = $reason;
        }

        return self::new(
            ErrorCode::Cancelled,
            ($reason !== null && $reason !== '') ? $reason : self::DEFAULT_REASON,
            details: $details !== [] ? $details : null,
        );
    }

    /**
     * Get the cancellation token if available.
     *
     * @return null|string The cancellation token that was used to cancel this request,
     *                     or null if token was not included in the error details
     */
    public function getToken(): ?string
    {
        $token = $this->error->details['token'] ?? null;

        return is_string($token) ? $token : null;
    }

    /**
     * Get the cancellation reason if available.
     *
     * @return null|string The human-readable reason for cancellation, or null if no
     *                     specific reason was provided
     */
    public function getReason(): ?string
    {
        $reason = $this->error->details['reason'] ?? null;

        return is_string($reason) ? $reason : null;
    }
}

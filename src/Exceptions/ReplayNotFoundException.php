<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Cline\Forrst\Enums\ErrorCode;

use function sprintf;

/**
 * Exception thrown when a replay record does not exist.
 *
 * Represents Forrst error code REPLAY_NOT_FOUND, thrown when a client attempts
 * to access, execute, or cancel a replay using an ID that does not exist in
 * the replay storage. This is a non-retryable error indicating the replay ID
 * is invalid or the replay was never created. The exception includes the
 * requested replay ID for client-side error correlation and debugging.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/extensions/replay Replay extension specification
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 */
final class ReplayNotFoundException extends AbstractRequestException
{
    /**
     * Create a replay not found exception.
     *
     * @param string $replayId Unique identifier of the replay that was requested but
     *                         does not exist in replay storage. Included in the error
     *                         response for debugging and error correlation.
     *
     * @return self Forrst exception with REPLAY_NOT_FOUND error code and the requested
     *              replay_id in structured error details
     */
    public static function create(string $replayId): self
    {
        return self::new(
            ErrorCode::ReplayNotFound,
            sprintf("Replay record '%s' not found", $replayId),
            details: [
                'replay_id' => $replayId,
            ],
        );
    }

    /**
     * Get the replay ID that was not found.
     *
     * @return null|string Unique identifier of the replay that does not exist, or null
     *                     if not set in the error details
     */
    public function getReplayId(): ?string
    {
        // @phpstan-ignore-next-line return.type
        return $this->error->details['replay_id'] ?? null;
    }
}

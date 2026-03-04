<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Cline\Forrst\Enums\ErrorCode;
use DateTimeInterface;

use function sprintf;

/**
 * Exception thrown when a replay record has exceeded its time-to-live.
 *
 * Represents Forrst error code REPLAY_EXPIRED, thrown when a client attempts
 * to execute or access a replay whose TTL (time-to-live) has been exceeded.
 * This is a non-retryable error indicating the replay has aged out and can
 * no longer be processed. The exception includes the replay ID and optional
 * expiration timestamp to help clients understand when the replay became invalid.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/extensions/replay Replay extension specification
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 */
final class ReplayExpiredException extends AbstractRequestException
{
    /**
     * Create a replay expired exception.
     *
     * @param string                 $replayId  Unique identifier of the expired replay record. Used for
     *                                          error correlation and debugging to identify which replay
     *                                          operation was attempted on an expired record.
     * @param null|DateTimeInterface $expiredAt Timestamp when the replay's TTL was exceeded
     *                                          and the record became invalid. Formatted as
     *                                          RFC3339 string in the error response for audit
     *                                          logging and client-side TTL tracking.
     *
     * @return self Forrst exception with REPLAY_EXPIRED error code and structured error
     *              details including replay_id and optional expired_at timestamp
     */
    public static function create(string $replayId, ?DateTimeInterface $expiredAt = null): self
    {
        $details = [
            'replay_id' => $replayId,
        ];

        if ($expiredAt instanceof DateTimeInterface) {
            $details['expired_at'] = $expiredAt->format(DateTimeInterface::RFC3339);
        }

        return self::new(
            ErrorCode::ReplayExpired,
            sprintf("Replay record '%s' has expired", $replayId),
            details: $details,
        );
    }

    /**
     * Get the replay ID that expired.
     *
     * @return null|string Unique identifier of the expired replay record, or null if
     *                     not set in the error details
     */
    public function getReplayId(): ?string
    {
        // @phpstan-ignore-next-line return.type
        return $this->error->details['replay_id'] ?? null;
    }

    /**
     * Get when the replay expired.
     *
     * @return null|string RFC3339 formatted timestamp indicating when the replay's TTL
     *                     was exceeded, or null if expiration time was not recorded
     */
    public function getExpiredAt(): ?string
    {
        // @phpstan-ignore-next-line return.type
        return $this->error->details['expired_at'] ?? null;
    }
}

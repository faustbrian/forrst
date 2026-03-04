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
 * Exception thrown when attempting to operate on a cancelled replay record.
 *
 * Represents Forrst error code REPLAY_CANCELLED, thrown when a client attempts
 * to execute, modify, or access a replay that has been explicitly cancelled.
 * This is a non-retryable error indicating the replay has been invalidated and
 * cannot be processed. The exception includes the replay ID and optional
 * cancellation timestamp for client-side error tracking and audit purposes.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/extensions/replay Replay extension specification
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 */
final class ReplayCancelledException extends AbstractRequestException
{
    /**
     * Create a replay cancelled exception.
     *
     * @param string                 $replayId    Unique identifier of the cancelled replay record. Used for
     *                                            error correlation and debugging to identify which replay
     *                                            operation was attempted on a cancelled record.
     * @param null|DateTimeInterface $cancelledAt Timestamp when the replay was cancelled.
     *                                            Formatted as RFC3339 string in the error
     *                                            response for audit logging and client-side
     *                                            tracking of cancellation events.
     *
     * @return self Forrst exception with REPLAY_CANCELLED error code and structured error
     *              details including replay_id and optional cancelled_at timestamp
     */
    public static function create(string $replayId, ?DateTimeInterface $cancelledAt = null): self
    {
        $details = [
            'replay_id' => $replayId,
        ];

        if ($cancelledAt instanceof DateTimeInterface) {
            $details['cancelled_at'] = $cancelledAt->format(DateTimeInterface::RFC3339);
        }

        return self::new(
            ErrorCode::ReplayCancelled,
            sprintf("Replay record '%s' has been cancelled", $replayId),
            details: $details,
        );
    }

    /**
     * Get the replay ID that was cancelled.
     *
     * @return null|string Unique identifier of the cancelled replay record, or null if
     *                     not set in the error details
     */
    public function getReplayId(): ?string
    {
        // @phpstan-ignore-next-line return.type
        return $this->error->details['replay_id'] ?? null;
    }

    /**
     * Get when the replay was cancelled.
     *
     * @return null|string RFC3339 formatted timestamp indicating when the replay was
     *                     cancelled, or null if cancellation time was not recorded
     */
    public function getCancelledAt(): ?string
    {
        // @phpstan-ignore-next-line return.type
        return $this->error->details['cancelled_at'] ?? null;
    }
}

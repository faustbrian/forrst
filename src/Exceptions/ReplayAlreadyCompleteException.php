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
 * Exception thrown when attempting to operate on a completed replay record.
 *
 * Represents Forrst error code REPLAY_ALREADY_COMPLETE, thrown when a client attempts
 * to cancel, modify, or re-execute a replay that has already been successfully
 * processed and marked as complete. This is a non-retryable error that indicates
 * the replay lifecycle has ended and no further operations can be performed on
 * this replay record. Includes the replay ID and optional completion timestamp
 * for client-side tracking and error reporting.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/extensions/replay Replay extension specification
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 */
final class ReplayAlreadyCompleteException extends AbstractRequestException
{
    /**
     * Create a replay already complete exception.
     *
     * @param string                 $replayId    Unique identifier of the replay record that has already
     *                                            been completed. Used for client-side error correlation
     *                                            and debugging to identify which replay operation failed.
     * @param null|DateTimeInterface $completedAt Timestamp when the replay was marked as
     *                                            complete. Formatted as RFC3339 string in
     *                                            the error response to provide precise
     *                                            completion tracking for audit logs and
     *                                            client-side error reporting.
     *
     * @return self Forrst exception with REPLAY_ALREADY_COMPLETE error code and structured
     *              error details including replay_id and optional completed_at timestamp
     */
    public static function create(string $replayId, ?DateTimeInterface $completedAt = null): self
    {
        $details = [
            'replay_id' => $replayId,
        ];

        if ($completedAt instanceof DateTimeInterface) {
            $details['completed_at'] = $completedAt->format(DateTimeInterface::RFC3339);
        }

        return self::new(
            ErrorCode::ReplayAlreadyComplete,
            sprintf("Replay record '%s' has already been completed", $replayId),
            details: $details,
        );
    }

    /**
     * Get the replay ID that was already completed.
     *
     * @return null|string Unique identifier of the completed replay record, or null if
     *                     not set in the error details
     */
    public function getReplayId(): ?string
    {
        // @phpstan-ignore-next-line return.type
        return $this->error->details['replay_id'] ?? null;
    }

    /**
     * Get when the replay was completed.
     *
     * @return null|string RFC3339 formatted timestamp indicating when the replay was
     *                     marked as complete, or null if completion time was not recorded
     */
    public function getCompletedAt(): ?string
    {
        // @phpstan-ignore-next-line return.type
        return $this->error->details['completed_at'] ?? null;
    }
}

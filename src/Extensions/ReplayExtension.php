<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions;

use Carbon\CarbonImmutable;
use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Enums\ReplayPriority;
use Cline\Forrst\Enums\ReplayStatus;
use Cline\Forrst\Enums\TimeUnit;
use DateTimeImmutable;
use DateTimeInterface;
use Override;

use function sprintf;

/**
 * Request replay extension handler.
 *
 * Enables servers to queue requests that cannot be processed immediately due to
 * temporary unavailability, resource constraints, or maintenance windows. Queued
 * requests are automatically replayed when the system recovers. Differs from
 * idempotency (which caches completed results) by recording pending requests for
 * future execution. Provides tracking, callbacks, and priority-based replay queuing.
 *
 * Request options:
 * - enabled: Whether to enable replay queuing for this request
 * - ttl: Maximum time to retain queued request before expiration (value and unit)
 * - priority: Replay priority for queue ordering (high, normal, low)
 * - callback: Webhook URL to notify when replay completes or fails
 *
 * Response data:
 * - status: Request status (processed, queued, processing, completed, failed, expired, cancelled)
 * - replay_id: Unique identifier for tracking and managing the queued request
 * - queued_at: ISO 8601 timestamp when request was queued
 * - expires_at: ISO 8601 timestamp when queued request will be discarded
 * - reason: Human-readable explanation for why the request was queued
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/replay
 */
final class ReplayExtension extends AbstractExtension
{
    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function getUrn(): string
    {
        return ExtensionUrn::Replay->value;
    }

    /**
     * {@inheritDoc}
     *
     * Replay errors are not fatal - they indicate the replay record status.
     */
    #[Override()]
    public function isErrorFatal(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     *
     * Replay extension is not global - it only applies when explicitly requested.
     */
    #[Override()]
    public function isGlobal(): bool
    {
        return false;
    }

    /**
     * Build replay request options.
     *
     * Creates the options object for including replay extension in a request.
     * Configures replay behavior including TTL, priority, and callback webhook.
     *
     * @param  bool                      $enabled  Whether to enable replay for this request
     * @param  null|array<string, mixed> $ttl      TTL duration object with value and unit (optional)
     * @param  null|ReplayPriority       $priority Queue priority for replay ordering (optional)
     * @param  null|array<string, mixed> $callback Webhook callback configuration (optional)
     * @return array<string, mixed>      Extension options for request
     */
    public function buildOptions(
        bool $enabled = true,
        ?array $ttl = null,
        ?ReplayPriority $priority = null,
        ?array $callback = null,
    ): array {
        $options = [
            'enabled' => $enabled,
        ];

        if ($ttl !== null) {
            $options['ttl'] = $ttl;
        }

        if ($priority instanceof ReplayPriority) {
            $options['priority'] = $priority->value;
        }

        if ($callback !== null) {
            $options['callback'] = $callback;
        }

        return $options;
    }

    /**
     * Build a time duration object.
     *
     * @param  int                  $value The numeric value
     * @param  TimeUnit             $unit  The time unit
     * @return array<string, mixed> Duration object with value and unit
     */
    public function buildDuration(int $value, TimeUnit $unit): array
    {
        return [
            'value' => $value,
            'unit' => $unit->value,
        ];
    }

    /**
     * Build a callback configuration.
     *
     * @param  string                     $url     Webhook URL
     * @param  null|array<string, string> $headers Optional headers
     * @return array<string, mixed>       Callback configuration
     */
    public function buildCallback(string $url, ?array $headers = null): array
    {
        $callback = [
            'url' => $url,
        ];

        if ($headers !== null) {
            $callback['headers'] = $headers;
        }

        return $callback;
    }

    /**
     * Build response data for a processed request.
     *
     * @param  string               $replayId Unique replay ID
     * @return array<string, mixed> Processed response data
     */
    public function buildProcessedResponse(string $replayId): array
    {
        return [
            'status' => ReplayStatus::Processed->value,
            'replay_id' => $replayId,
        ];
    }

    /**
     * Build response data for a queued request.
     *
     * Creates response metadata for a request that was queued for later replay.
     * Includes tracking ID, timing information, queue position, and estimated
     * replay time to help clients monitor queued requests.
     *
     * @param  string                    $replayId        Unique identifier for tracking this queued request
     * @param  string                    $reason          Human-readable explanation for queuing
     * @param  DateTimeInterface         $queuedAt        ISO 8601 timestamp when request entered queue
     * @param  DateTimeInterface         $expiresAt       ISO 8601 timestamp when request will be discarded
     * @param  null|int                  $position        Current position in the replay queue (optional)
     * @param  null|array<string, mixed> $estimatedReplay Estimated replay time as duration object (optional)
     * @return array<string, mixed>      Queued status response metadata
     */
    public function buildQueuedResponse(
        string $replayId,
        string $reason,
        DateTimeInterface $queuedAt,
        DateTimeInterface $expiresAt,
        ?int $position = null,
        ?array $estimatedReplay = null,
    ): array {
        $data = [
            'status' => ReplayStatus::Queued->value,
            'replay_id' => $replayId,
            'reason' => $reason,
            'queued_at' => $queuedAt->format(DateTimeInterface::RFC3339),
            'expires_at' => $expiresAt->format(DateTimeInterface::RFC3339),
        ];

        if ($position !== null) {
            $data['position'] = $position;
        }

        if ($estimatedReplay !== null) {
            $data['estimated_replay'] = $estimatedReplay;
        }

        return $data;
    }

    /**
     * Build response data for a completed replay.
     *
     * @param  string               $replayId   Unique replay ID
     * @param  DateTimeInterface    $queuedAt   When request was queued
     * @param  DateTimeInterface    $replayedAt When request was replayed
     * @param  int                  $attempts   Number of replay attempts
     * @return array<string, mixed> Completed response data
     */
    public function buildCompletedResponse(
        string $replayId,
        DateTimeInterface $queuedAt,
        DateTimeInterface $replayedAt,
        int $attempts = 1,
    ): array {
        return [
            'status' => ReplayStatus::Completed->value,
            'replay_id' => $replayId,
            'queued_at' => $queuedAt->format(DateTimeInterface::RFC3339),
            'replayed_at' => $replayedAt->format(DateTimeInterface::RFC3339),
            'attempts' => $attempts,
        ];
    }

    /**
     * Build response data for a failed replay.
     *
     * @param  string               $replayId   Unique replay ID
     * @param  DateTimeInterface    $queuedAt   When request was queued
     * @param  DateTimeInterface    $replayedAt When replay was attempted
     * @param  int                  $attempts   Number of replay attempts
     * @return array<string, mixed> Failed response data
     */
    public function buildFailedResponse(
        string $replayId,
        DateTimeInterface $queuedAt,
        DateTimeInterface $replayedAt,
        int $attempts,
    ): array {
        return [
            'status' => ReplayStatus::Failed->value,
            'replay_id' => $replayId,
            'queued_at' => $queuedAt->format(DateTimeInterface::RFC3339),
            'replayed_at' => $replayedAt->format(DateTimeInterface::RFC3339),
            'attempts' => $attempts,
        ];
    }

    /**
     * Build response data for a cancelled replay.
     *
     * @param  string               $replayId    Unique replay ID
     * @param  DateTimeInterface    $cancelledAt When request was cancelled
     * @return array<string, mixed> Cancelled response data
     */
    public function buildCancelledResponse(
        string $replayId,
        DateTimeInterface $cancelledAt,
    ): array {
        return [
            'status' => ReplayStatus::Cancelled->value,
            'replay_id' => $replayId,
            'cancelled_at' => $cancelledAt->format(DateTimeInterface::RFC3339),
        ];
    }

    /**
     * Build response data for an expired replay.
     *
     * @param  string               $replayId  Unique replay ID
     * @param  DateTimeInterface    $expiredAt When request expired
     * @return array<string, mixed> Expired response data
     */
    public function buildExpiredResponse(
        string $replayId,
        DateTimeInterface $expiredAt,
    ): array {
        return [
            'status' => ReplayStatus::Expired->value,
            'replay_id' => $replayId,
            'expired_at' => $expiredAt->format(DateTimeInterface::RFC3339),
        ];
    }

    /**
     * Build response data for a processing replay.
     *
     * @param  string               $replayId    Unique replay ID
     * @param  DateTimeInterface    $triggeredAt When replay was triggered
     * @return array<string, mixed> Processing response data
     */
    public function buildProcessingResponse(
        string $replayId,
        DateTimeInterface $triggeredAt,
    ): array {
        return [
            'status' => ReplayStatus::Processing->value,
            'replay_id' => $replayId,
            'triggered_at' => $triggeredAt->format(DateTimeInterface::RFC3339),
        ];
    }

    /**
     * Enrich a response with replay information.
     *
     * @param  ResponseData         $response   Original response
     * @param  array<string, mixed> $replayData Replay data
     * @return ResponseData         Enriched response
     */
    public function enrichResponse(ResponseData $response, array $replayData): ResponseData
    {
        $extensions = $response->extensions ?? [];
        $extensions[] = ExtensionData::response(ExtensionUrn::Replay->value, $replayData);

        return new ResponseData(
            protocol: $response->protocol,
            id: $response->id,
            result: $response->result,
            errors: $response->errors,
            extensions: $extensions,
            meta: $response->meta,
        );
    }

    /**
     * Calculate TTL in seconds.
     *
     * @param  int      $value TTL value
     * @param  TimeUnit $unit  TTL unit
     * @return int      Seconds
     */
    public function calculateTtlSeconds(int $value, TimeUnit $unit): int
    {
        return $unit->toSeconds($value);
    }

    /**
     * Calculate expiration timestamp from TTL.
     *
     * @param  int               $value TTL value
     * @param  TimeUnit          $unit  TTL unit
     * @return DateTimeImmutable Expiration timestamp
     */
    public function calculateExpiresAt(int $value, TimeUnit $unit): DateTimeImmutable
    {
        $seconds = $this->calculateTtlSeconds($value, $unit);

        return new DateTimeImmutable(sprintf('+%d seconds', $seconds));
    }

    /**
     * Check if a replay has expired.
     *
     * @param  DateTimeInterface $expiresAt Expiration timestamp
     * @return bool              True if expired
     */
    public function isExpired(DateTimeInterface $expiresAt): bool
    {
        return CarbonImmutable::now() >= $expiresAt;
    }

    /**
     * Check if a status is a terminal state.
     *
     * @param  ReplayStatus $status Replay status
     * @return bool         True if terminal
     */
    public function isTerminalStatus(ReplayStatus $status): bool
    {
        return $status->isTerminal();
    }

    /**
     * Validate priority value.
     *
     * @param  ReplayPriority $priority Priority value
     * @return bool           Always true (enum enforces valid values)
     */
    public function isValidPriority(ReplayPriority $priority): bool
    {
        return true;
    }
}

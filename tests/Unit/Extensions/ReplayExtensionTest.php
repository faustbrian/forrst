<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\Forrst\Data\ErrorData;
use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Enums\ReplayPriority;
use Cline\Forrst\Enums\ReplayStatus;
use Cline\Forrst\Enums\TimeUnit;
use Cline\Forrst\Extensions\ExtensionUrn;
use Cline\Forrst\Extensions\ReplayExtension;

describe('ReplayExtension', function (): void {
    describe('Happy Paths', function (): void {
        test('getUrn returns correct URN constant', function (): void {
            // Arrange
            $extension = new ReplayExtension();

            // Act
            $urn = $extension->getUrn();

            // Assert
            expect($urn)->toBe(ExtensionUrn::Replay->value)
                ->and($urn)->toBe('urn:cline:forrst:ext:replay');
        });

        test('isErrorFatal returns false for replay extension', function (): void {
            // Arrange
            $extension = new ReplayExtension();

            // Act
            $result = $extension->isErrorFatal();

            // Assert
            expect($result)->toBeFalse();
        });

        test('isGlobal returns false for replay extension', function (): void {
            // Arrange
            $extension = new ReplayExtension();

            // Act
            $result = $extension->isGlobal();

            // Assert
            expect($result)->toBeFalse();
        });

        test('buildDuration creates duration object', function (): void {
            // Arrange
            $extension = new ReplayExtension();

            // Act
            $duration = $extension->buildDuration(24, TimeUnit::Hour);

            // Assert
            expect($duration)->toBe([
                'value' => 24,
                'unit' => 'hour',
            ]);
        });

        test('buildOptions creates complete options', function (): void {
            // Arrange
            $extension = new ReplayExtension();

            // Act
            $options = $extension->buildOptions(
                enabled: true,
                ttl: ['value' => 24, 'unit' => 'hour'],
                priority: ReplayPriority::High,
                callback: ['url' => 'https://example.com/webhook'],
            );

            // Assert
            expect($options)->toHaveKey('enabled', true)
                ->and($options)->toHaveKey('ttl', ['value' => 24, 'unit' => 'hour'])
                ->and($options)->toHaveKey('priority', 'high')
                ->and($options)->toHaveKey('callback', ['url' => 'https://example.com/webhook']);
        });

        test('buildCallback creates callback configuration', function (): void {
            // Arrange
            $extension = new ReplayExtension();

            // Act
            $callback = $extension->buildCallback(
                url: 'https://api.example.com/webhooks/replay',
                headers: ['Authorization' => 'Bearer token123'],
            );

            // Assert
            expect($callback)->toHaveKey('url', 'https://api.example.com/webhooks/replay')
                ->and($callback)->toHaveKey('headers', ['Authorization' => 'Bearer token123']);
        });

        test('buildProcessedResponse creates processed data', function (): void {
            // Arrange
            $extension = new ReplayExtension();

            // Act
            $data = $extension->buildProcessedResponse('rpl_abc123');

            // Assert
            expect($data)->toHaveKey('status', ReplayStatus::Processed->value)
                ->and($data)->toHaveKey('replay_id', 'rpl_abc123');
        });

        test('buildQueuedResponse creates complete queued data', function (): void {
            // Arrange
            $extension = new ReplayExtension();
            $queuedAt = CarbonImmutable::parse('2024-01-15T10:00:00Z');
            $expiresAt = CarbonImmutable::parse('2024-01-16T10:00:00Z');

            // Act
            $data = $extension->buildQueuedResponse(
                replayId: 'rpl_abc123',
                reason: 'SERVER_MAINTENANCE',
                queuedAt: $queuedAt,
                expiresAt: $expiresAt,
                position: 42,
                estimatedReplay: ['value' => 2, 'unit' => 'hour'],
            );

            // Assert
            expect($data)->toHaveKey('status', ReplayStatus::Queued->value)
                ->and($data)->toHaveKey('replay_id', 'rpl_abc123')
                ->and($data)->toHaveKey('reason', 'SERVER_MAINTENANCE')
                ->and($data)->toHaveKey('queued_at', '2024-01-15T10:00:00+00:00')
                ->and($data)->toHaveKey('expires_at', '2024-01-16T10:00:00+00:00')
                ->and($data)->toHaveKey('position', 42)
                ->and($data)->toHaveKey('estimated_replay', ['value' => 2, 'unit' => 'hour']);
        });

        test('buildCompletedResponse creates completed data', function (): void {
            // Arrange
            $extension = new ReplayExtension();
            $queuedAt = CarbonImmutable::parse('2024-01-15T10:00:00Z');
            $replayedAt = CarbonImmutable::parse('2024-01-15T12:00:00Z');

            // Act
            $data = $extension->buildCompletedResponse(
                replayId: 'rpl_abc123',
                queuedAt: $queuedAt,
                replayedAt: $replayedAt,
                attempts: 1,
            );

            // Assert
            expect($data)->toHaveKey('status', ReplayStatus::Completed->value)
                ->and($data)->toHaveKey('replay_id', 'rpl_abc123')
                ->and($data)->toHaveKey('queued_at', '2024-01-15T10:00:00+00:00')
                ->and($data)->toHaveKey('replayed_at', '2024-01-15T12:00:00+00:00')
                ->and($data)->toHaveKey('attempts', 1);
        });

        test('buildFailedResponse creates failed data', function (): void {
            // Arrange
            $extension = new ReplayExtension();
            $queuedAt = CarbonImmutable::parse('2024-01-15T10:00:00Z');
            $replayedAt = CarbonImmutable::parse('2024-01-15T12:00:00Z');

            // Act
            $data = $extension->buildFailedResponse(
                replayId: 'rpl_abc123',
                queuedAt: $queuedAt,
                replayedAt: $replayedAt,
                attempts: 3,
            );

            // Assert
            expect($data)->toHaveKey('status', ReplayStatus::Failed->value)
                ->and($data)->toHaveKey('replay_id', 'rpl_abc123')
                ->and($data)->toHaveKey('attempts', 3);
        });

        test('buildCancelledResponse creates cancelled data', function (): void {
            // Arrange
            $extension = new ReplayExtension();
            $cancelledAt = CarbonImmutable::parse('2024-01-15T11:00:00Z');

            // Act
            $data = $extension->buildCancelledResponse(
                replayId: 'rpl_abc123',
                cancelledAt: $cancelledAt,
            );

            // Assert
            expect($data)->toHaveKey('status', ReplayStatus::Cancelled->value)
                ->and($data)->toHaveKey('replay_id', 'rpl_abc123')
                ->and($data)->toHaveKey('cancelled_at', '2024-01-15T11:00:00+00:00');
        });

        test('buildExpiredResponse creates expired data', function (): void {
            // Arrange
            $extension = new ReplayExtension();
            $expiredAt = CarbonImmutable::parse('2024-01-16T10:00:00Z');

            // Act
            $data = $extension->buildExpiredResponse(
                replayId: 'rpl_abc123',
                expiredAt: $expiredAt,
            );

            // Assert
            expect($data)->toHaveKey('status', ReplayStatus::Expired->value)
                ->and($data)->toHaveKey('replay_id', 'rpl_abc123')
                ->and($data)->toHaveKey('expired_at', '2024-01-16T10:00:00+00:00');
        });

        test('buildProcessingResponse creates processing data', function (): void {
            // Arrange
            $extension = new ReplayExtension();
            $triggeredAt = CarbonImmutable::parse('2024-01-15T11:30:00Z');

            // Act
            $data = $extension->buildProcessingResponse(
                replayId: 'rpl_abc123',
                triggeredAt: $triggeredAt,
            );

            // Assert
            expect($data)->toHaveKey('status', ReplayStatus::Processing->value)
                ->and($data)->toHaveKey('replay_id', 'rpl_abc123')
                ->and($data)->toHaveKey('triggered_at', '2024-01-15T11:30:00+00:00');
        });

        test('enrichResponse adds replay extension to response', function (): void {
            // Arrange
            $extension = new ReplayExtension();
            $response = ResponseData::success(['order_id' => 456], '01JFEX0001');
            $replayData = $extension->buildProcessedResponse('rpl_abc123');

            // Act
            $enriched = $extension->enrichResponse($response, $replayData);

            // Assert
            expect($enriched)->toBeInstanceOf(ResponseData::class)
                ->and($enriched->extensions)->toHaveCount(1);

            $ext = $enriched->extensions[0];
            expect($ext->urn)->toBe(ExtensionUrn::Replay->value)
                ->and($ext->data)->toHaveKey('status', 'processed');
        });

        test('calculateTtlSeconds converts seconds correctly', function (): void {
            // Arrange
            $extension = new ReplayExtension();

            // Act
            $seconds = $extension->calculateTtlSeconds(30, TimeUnit::Second);

            // Assert
            expect($seconds)->toBe(30);
        });

        test('calculateTtlSeconds converts minutes correctly', function (): void {
            // Arrange
            $extension = new ReplayExtension();

            // Act
            $seconds = $extension->calculateTtlSeconds(5, TimeUnit::Minute);

            // Assert
            expect($seconds)->toBe(300);
        });

        test('calculateTtlSeconds converts hours correctly', function (): void {
            // Arrange
            $extension = new ReplayExtension();

            // Act
            $seconds = $extension->calculateTtlSeconds(2, TimeUnit::Hour);

            // Assert
            expect($seconds)->toBe(7_200);
        });

        test('calculateTtlSeconds converts days correctly', function (): void {
            // Arrange
            $extension = new ReplayExtension();

            // Act
            $seconds = $extension->calculateTtlSeconds(1, TimeUnit::Day);

            // Assert
            expect($seconds)->toBe(86_400);
        });

        test('calculateExpiresAt returns future timestamp', function (): void {
            // Arrange
            $extension = new ReplayExtension();
            $now = CarbonImmutable::now();

            // Act
            $expiresAt = $extension->calculateExpiresAt(1, TimeUnit::Hour);

            // Assert
            expect($expiresAt)->toBeInstanceOf(DateTimeImmutable::class)
                ->and($expiresAt->getTimestamp())->toBeGreaterThan($now->getTimestamp());
        });

        test('isExpired returns true for past timestamp', function (): void {
            // Arrange
            $extension = new ReplayExtension();
            $pastTime = CarbonImmutable::now()->subHours(1);

            // Act
            $expired = $extension->isExpired($pastTime);

            // Assert
            expect($expired)->toBeTrue();
        });

        test('isExpired returns false for future timestamp', function (): void {
            // Arrange
            $extension = new ReplayExtension();
            $futureTime = CarbonImmutable::now()->addHours(1);

            // Act
            $expired = $extension->isExpired($futureTime);

            // Assert
            expect($expired)->toBeFalse();
        });

        test('isTerminalStatus returns true for terminal statuses', function (): void {
            // Arrange
            $extension = new ReplayExtension();

            // Act & Assert
            expect($extension->isTerminalStatus(ReplayStatus::Completed))->toBeTrue()
                ->and($extension->isTerminalStatus(ReplayStatus::Failed))->toBeTrue()
                ->and($extension->isTerminalStatus(ReplayStatus::Expired))->toBeTrue()
                ->and($extension->isTerminalStatus(ReplayStatus::Cancelled))->toBeTrue()
                ->and($extension->isTerminalStatus(ReplayStatus::Processed))->toBeTrue();
        });

        test('isTerminalStatus returns false for non-terminal statuses', function (): void {
            // Arrange
            $extension = new ReplayExtension();

            // Act & Assert
            expect($extension->isTerminalStatus(ReplayStatus::Queued))->toBeFalse()
                ->and($extension->isTerminalStatus(ReplayStatus::Processing))->toBeFalse();
        });

        test('isValidPriority returns true for all priorities', function (): void {
            // Arrange
            $extension = new ReplayExtension();

            // Act & Assert
            expect($extension->isValidPriority(ReplayPriority::High))->toBeTrue()
                ->and($extension->isValidPriority(ReplayPriority::Normal))->toBeTrue()
                ->and($extension->isValidPriority(ReplayPriority::Low))->toBeTrue();
        });
    });

    describe('Edge Cases', function (): void {
        test('buildOptions with minimal parameters', function (): void {
            // Arrange
            $extension = new ReplayExtension();

            // Act
            $options = $extension->buildOptions();

            // Assert
            expect($options)->toBe(['enabled' => true])
                ->and($options)->not->toHaveKey('ttl')
                ->and($options)->not->toHaveKey('priority')
                ->and($options)->not->toHaveKey('callback');
        });

        test('buildQueuedResponse without optional fields', function (): void {
            // Arrange
            $extension = new ReplayExtension();
            $queuedAt = CarbonImmutable::parse('2024-01-15T10:00:00Z');
            $expiresAt = CarbonImmutable::parse('2024-01-16T10:00:00Z');

            // Act
            $data = $extension->buildQueuedResponse(
                replayId: 'rpl_abc123',
                reason: 'SERVER_MAINTENANCE',
                queuedAt: $queuedAt,
                expiresAt: $expiresAt,
            );

            // Assert
            expect($data)->not->toHaveKey('position')
                ->and($data)->not->toHaveKey('estimated_replay');
        });

        test('buildCallback without headers', function (): void {
            // Arrange
            $extension = new ReplayExtension();

            // Act
            $callback = $extension->buildCallback('https://example.com/webhook');

            // Assert
            expect($callback)->toBe(['url' => 'https://example.com/webhook'])
                ->and($callback)->not->toHaveKey('headers');
        });

        test('enrichResponse appends to existing extensions', function (): void {
            // Arrange
            $extension = new ReplayExtension();
            $existingExt = ExtensionData::response('urn:cline:forrst:ext:other', ['data' => 'test']);
            $response = new ResponseData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0002',
                result: ['ok' => true],
                extensions: [$existingExt],
            );
            $replayData = $extension->buildProcessedResponse('rpl_abc123');

            // Act
            $enriched = $extension->enrichResponse($response, $replayData);

            // Assert
            expect($enriched->extensions)->toHaveCount(2);
        });

        test('ReplayStatus enum has correct values', function (): void {
            expect(ReplayStatus::Queued->value)->toBe('queued')
                ->and(ReplayStatus::Processing->value)->toBe('processing')
                ->and(ReplayStatus::Completed->value)->toBe('completed')
                ->and(ReplayStatus::Failed->value)->toBe('failed')
                ->and(ReplayStatus::Expired->value)->toBe('expired')
                ->and(ReplayStatus::Cancelled->value)->toBe('cancelled')
                ->and(ReplayStatus::Processed->value)->toBe('processed');
        });

        test('ReplayPriority enum has correct values', function (): void {
            expect(ReplayPriority::High->value)->toBe('high')
                ->and(ReplayPriority::Normal->value)->toBe('normal')
                ->and(ReplayPriority::Low->value)->toBe('low');
        });

        test('TimeUnit enum has correct values', function (): void {
            expect(TimeUnit::Second->value)->toBe('second')
                ->and(TimeUnit::Minute->value)->toBe('minute')
                ->and(TimeUnit::Hour->value)->toBe('hour')
                ->and(TimeUnit::Day->value)->toBe('day');
        });

        test('TimeUnit toSeconds converts correctly', function (): void {
            expect(TimeUnit::Second->toSeconds(30))->toBe(30)
                ->and(TimeUnit::Minute->toSeconds(5))->toBe(300)
                ->and(TimeUnit::Hour->toSeconds(2))->toBe(7_200)
                ->and(TimeUnit::Day->toSeconds(1))->toBe(86_400);
        });

        test('ReplayStatus isTerminal returns correct values', function (): void {
            expect(ReplayStatus::Completed->isTerminal())->toBeTrue()
                ->and(ReplayStatus::Failed->isTerminal())->toBeTrue()
                ->and(ReplayStatus::Expired->isTerminal())->toBeTrue()
                ->and(ReplayStatus::Cancelled->isTerminal())->toBeTrue()
                ->and(ReplayStatus::Processed->isTerminal())->toBeTrue()
                ->and(ReplayStatus::Queued->isTerminal())->toBeFalse()
                ->and(ReplayStatus::Processing->isTerminal())->toBeFalse();
        });
    });

    describe('Sad Paths', function (): void {
        test('enrichResponse preserves error responses', function (): void {
            // Arrange
            $extension = new ReplayExtension();
            $errorData = new ErrorData(
                code: ErrorCode::ReplayNotFound,
                message: 'Replay not found',
            );
            $response = ResponseData::error($errorData, '01JFEX0003');
            $replayData = $extension->buildExpiredResponse(
                replayId: 'rpl_abc123',
                expiredAt: CarbonImmutable::now(),
            );

            // Act
            $enriched = $extension->enrichResponse($response, $replayData);

            // Assert
            expect($enriched->result)->toBeNull()
                ->and($enriched->errors)->toHaveCount(1)
                ->and($enriched->extensions)->toHaveCount(1);
        });

        test('isExpired handles exact boundary', function (): void {
            // Arrange
            $extension = new ReplayExtension();
            $now = CarbonImmutable::now();

            // Act
            $expired = $extension->isExpired($now);

            // Assert - at exact boundary, should be true (now >= now)
            expect($expired)->toBeTrue();
        });
    });
});

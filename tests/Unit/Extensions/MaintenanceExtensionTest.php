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
use Cline\Forrst\Extensions\ExtensionUrn;
use Cline\Forrst\Extensions\MaintenanceExtension;

describe('MaintenanceExtension', function (): void {
    describe('Happy Paths', function (): void {
        test('getUrn returns correct URN constant', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();

            // Act
            $urn = $extension->getUrn();

            // Assert
            expect($urn)->toBe(ExtensionUrn::Maintenance->value)
                ->and($urn)->toBe('urn:cline:forrst:ext:maintenance');
        });

        test('isErrorFatal returns true for maintenance mode', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();

            // Act
            $result = $extension->isErrorFatal();

            // Assert
            expect($result)->toBeTrue();
        });

        test('isGlobal returns true for maintenance extension', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();

            // Act
            $result = $extension->isGlobal();

            // Assert
            expect($result)->toBeTrue();
        });

        test('buildDuration creates duration object', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();

            // Act
            $duration = $extension->buildDuration(30, 'minute');

            // Assert
            expect($duration)->toBe([
                'value' => 30,
                'unit' => 'minute',
            ]);
        });

        test('buildServerMaintenance creates complete server maintenance data', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();
            $startedAt = CarbonImmutable::parse('2024-01-15T10:00:00Z');
            $until = CarbonImmutable::parse('2024-01-15T12:00:00Z');

            // Act
            $data = $extension->buildServerMaintenance(
                reason: 'Database migration in progress',
                startedAt: $startedAt,
                until: $until,
                retryValue: 30,
                retryUnit: MaintenanceExtension::UNIT_MINUTE,
            );

            // Assert
            expect($data)->toHaveKey('scope', MaintenanceExtension::SCOPE_SERVER)
                ->and($data)->toHaveKey('reason', 'Database migration in progress')
                ->and($data)->toHaveKey('started_at', '2024-01-15T10:00:00+00:00')
                ->and($data)->toHaveKey('until', '2024-01-15T12:00:00+00:00')
                ->and($data)->toHaveKey('retry_after', ['value' => 30, 'unit' => 'minute']);
        });

        test('buildServerMaintenance works without until timestamp', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();
            $startedAt = CarbonImmutable::parse('2024-01-15T10:00:00Z');

            // Act
            $data = $extension->buildServerMaintenance(
                reason: 'Unknown duration maintenance',
                startedAt: $startedAt,
            );

            // Assert
            expect($data)->toHaveKey('scope', MaintenanceExtension::SCOPE_SERVER)
                ->and($data)->toHaveKey('reason')
                ->and($data)->toHaveKey('started_at')
                ->and($data)->not->toHaveKey('until');
        });

        test('buildFunctionMaintenance creates complete function maintenance data', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();
            $startedAt = CarbonImmutable::parse('2024-01-15T10:00:00Z');
            $until = CarbonImmutable::parse('2024-01-15T11:00:00Z');

            // Act
            $data = $extension->buildFunctionMaintenance(
                function: 'urn:cline:forrst:fn:reports:generate',
                reason: 'Report engine upgrade',
                startedAt: $startedAt,
                until: $until,
                retryValue: 15,
                retryUnit: MaintenanceExtension::UNIT_MINUTE,
            );

            // Assert
            expect($data)->toHaveKey('scope', MaintenanceExtension::SCOPE_FUNCTION)
                ->and($data)->toHaveKey('function', 'urn:cline:forrst:fn:reports:generate')
                ->and($data)->toHaveKey('reason', 'Report engine upgrade')
                ->and($data)->toHaveKey('started_at', '2024-01-15T10:00:00+00:00')
                ->and($data)->toHaveKey('until', '2024-01-15T11:00:00+00:00')
                ->and($data)->toHaveKey('retry_after', ['value' => 15, 'unit' => 'minute']);
        });

        test('buildServerMaintenanceErrorDetails creates error details', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();
            $startedAt = CarbonImmutable::parse('2024-01-15T10:00:00Z');
            $until = CarbonImmutable::parse('2024-01-15T12:00:00Z');

            // Act
            $details = $extension->buildServerMaintenanceErrorDetails(
                reason: 'Infrastructure upgrade',
                startedAt: $startedAt,
                until: $until,
                retryValue: 2,
                retryUnit: MaintenanceExtension::UNIT_HOUR,
            );

            // Assert
            expect($details)->toHaveKey('reason', 'Infrastructure upgrade')
                ->and($details)->toHaveKey('started_at')
                ->and($details)->toHaveKey('until')
                ->and($details)->toHaveKey('retry_after', ['value' => 2, 'unit' => 'hour']);
        });

        test('buildFunctionMaintenanceErrorDetails creates error details with function', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();
            $startedAt = CarbonImmutable::parse('2024-01-15T10:00:00Z');

            // Act
            $details = $extension->buildFunctionMaintenanceErrorDetails(
                function: 'urn:cline:forrst:fn:search:query',
                reason: 'Search index rebuild',
                startedAt: $startedAt,
            );

            // Assert
            expect($details)->toHaveKey('function', 'urn:cline:forrst:fn:search:query')
                ->and($details)->toHaveKey('reason', 'Search index rebuild')
                ->and($details)->toHaveKey('started_at')
                ->and($details)->not->toHaveKey('until');
        });

        test('enrichResponse adds maintenance extension to response', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();
            $response = ResponseData::success(['order_id' => 456], '01JFEX0001');
            $maintenance = $extension->buildServerMaintenance(
                reason: 'Scheduled maintenance',
                startedAt: CarbonImmutable::parse('2024-01-15T10:00:00Z'),
            );

            // Act
            $enriched = $extension->enrichResponse($response, $maintenance);

            // Assert
            expect($enriched)->toBeInstanceOf(ResponseData::class)
                ->and($enriched->extensions)->toHaveCount(1);

            $ext = $enriched->extensions[0];
            expect($ext->urn)->toBe(ExtensionUrn::Maintenance->value)
                ->and($ext->data)->toHaveKey('scope', 'server')
                ->and($ext->data)->toHaveKey('reason');
        });

        test('calculateRetryAfterSeconds converts seconds correctly', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();

            // Act
            $seconds = $extension->calculateRetryAfterSeconds(30, MaintenanceExtension::UNIT_SECOND);

            // Assert
            expect($seconds)->toBe(30);
        });

        test('calculateRetryAfterSeconds converts minutes correctly', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();

            // Act
            $seconds = $extension->calculateRetryAfterSeconds(5, MaintenanceExtension::UNIT_MINUTE);

            // Assert
            expect($seconds)->toBe(300);
        });

        test('calculateRetryAfterSeconds converts hours correctly', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();

            // Act
            $seconds = $extension->calculateRetryAfterSeconds(2, MaintenanceExtension::UNIT_HOUR);

            // Assert
            expect($seconds)->toBe(7_200);
        });

        test('isMaintenanceActive returns true for null until', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();

            // Act
            $active = $extension->isMaintenanceActive(null);

            // Assert
            expect($active)->toBeTrue();
        });

        test('isMaintenanceActive returns true for future until', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();
            $until = CarbonImmutable::now()->addHours(1);

            // Act
            $active = $extension->isMaintenanceActive($until);

            // Assert
            expect($active)->toBeTrue();
        });

        test('isMaintenanceActive returns false for past until', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();
            $until = CarbonImmutable::now()->subHours(1);

            // Act
            $active = $extension->isMaintenanceActive($until);

            // Assert
            expect($active)->toBeFalse();
        });

        test('calculateRemainingSeconds returns positive for future until', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();
            $until = CarbonImmutable::now()->addHours(1);

            // Act
            $remaining = $extension->calculateRemainingSeconds($until);

            // Assert
            expect($remaining)->toBeGreaterThan(3_500)
                ->and($remaining)->toBeLessThanOrEqual(3_600);
        });
    });

    describe('Edge Cases', function (): void {
        test('buildDuration with various time units', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();

            // Act & Assert
            expect($extension->buildDuration(30, MaintenanceExtension::UNIT_SECOND))
                ->toBe(['value' => 30, 'unit' => 'second']);

            expect($extension->buildDuration(15, MaintenanceExtension::UNIT_MINUTE))
                ->toBe(['value' => 15, 'unit' => 'minute']);

            expect($extension->buildDuration(2, MaintenanceExtension::UNIT_HOUR))
                ->toBe(['value' => 2, 'unit' => 'hour']);
        });

        test('enrichResponse appends to existing extensions', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();
            $existingExt = ExtensionData::response('urn:cline:forrst:ext:other', ['data' => 'test']);
            $response = new ResponseData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0002',
                result: ['ok' => true],
                extensions: [$existingExt],
            );
            $maintenance = $extension->buildServerMaintenance(
                reason: 'Test maintenance',
                startedAt: CarbonImmutable::now(),
            );

            // Act
            $enriched = $extension->enrichResponse($response, $maintenance);

            // Assert
            expect($enriched->extensions)->toHaveCount(2);
        });

        test('calculateRemainingSeconds returns zero for past until', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();
            $until = CarbonImmutable::now()->subHours(1);

            // Act
            $remaining = $extension->calculateRemainingSeconds($until);

            // Assert
            expect($remaining)->toBe(0);
        });

        test('calculateRetryAfterSeconds with unknown unit returns value as-is', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();

            // Act
            $seconds = $extension->calculateRetryAfterSeconds(30, 'unknown');

            // Assert
            expect($seconds)->toBe(30);
        });

        test('buildFunctionMaintenance works without until timestamp', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();
            $startedAt = CarbonImmutable::parse('2024-01-15T10:00:00Z');

            // Act
            $data = $extension->buildFunctionMaintenance(
                function: 'urn:cline:forrst:fn:reports:generate',
                reason: 'Unknown duration',
                startedAt: $startedAt,
            );

            // Assert
            expect($data)->not->toHaveKey('until');
        });

        test('scope constants have correct values', function (): void {
            expect(MaintenanceExtension::SCOPE_SERVER)->toBe('server')
                ->and(MaintenanceExtension::SCOPE_FUNCTION)->toBe('function');
        });

        test('unit constants have correct values', function (): void {
            expect(MaintenanceExtension::UNIT_SECOND)->toBe('second')
                ->and(MaintenanceExtension::UNIT_MINUTE)->toBe('minute')
                ->and(MaintenanceExtension::UNIT_HOUR)->toBe('hour');
        });
    });

    describe('Sad Paths', function (): void {
        test('enrichResponse preserves error responses', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();
            $errorData = new ErrorData(
                code: ErrorCode::ServerMaintenance,
                message: 'Server under maintenance',
            );
            $response = ResponseData::error($errorData, '01JFEX0003');
            $maintenance = $extension->buildServerMaintenance(
                reason: 'Maintenance in progress',
                startedAt: CarbonImmutable::now(),
            );

            // Act
            $enriched = $extension->enrichResponse($response, $maintenance);

            // Assert
            expect($enriched->result)->toBeNull()
                ->and($enriched->errors)->toHaveCount(1)
                ->and($enriched->extensions)->toHaveCount(1);
        });

        test('isMaintenanceActive handles exact boundary', function (): void {
            // Arrange
            $extension = new MaintenanceExtension();
            // Create a timestamp that's effectively "now"
            $until = CarbonImmutable::now();

            // Act
            $active = $extension->isMaintenanceActive($until);

            // Assert - at exact boundary, should be false (now is not < now)
            expect($active)->toBeFalse();
        });
    });
});

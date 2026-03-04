<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Extensions\ExtensionUrn;
use Cline\Forrst\Extensions\QuotaExtension;

describe('QuotaExtension', function (): void {
    describe('Happy Paths', function (): void {
        test('getUrn returns correct URN constant', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $urn = $extension->getUrn();

            // Assert
            expect($urn)->toBe(ExtensionUrn::Quota->value)
                ->and($urn)->toBe('urn:cline:forrst:ext:quota');
        });

        test('getIncludedTypes extracts include array from options', function (): void {
            // Arrange
            $extension = new QuotaExtension();
            $options = ['include' => ['requests', 'compute']];

            // Act
            $types = $extension->getIncludedTypes($options);

            // Assert
            expect($types)->toBe(['requests', 'compute']);
        });

        test('getIncludedTypes returns null when not specified', function (): void {
            // Arrange
            $extension = new QuotaExtension();
            $options = [];

            // Act
            $types = $extension->getIncludedTypes($options);

            // Assert
            expect($types)->toBeNull();
        });

        test('buildQuota creates complete quota entry', function (): void {
            // Arrange
            $extension = new QuotaExtension();
            $resetsAt = CarbonImmutable::parse('2025-01-01 00:00:00');

            // Act
            $quota = $extension->buildQuota(
                type: QuotaExtension::TYPE_REQUESTS,
                name: 'API Requests',
                limit: 1_000,
                used: 250,
                period: QuotaExtension::PERIOD_MONTH,
                unit: 'requests',
                resetsAt: $resetsAt,
            );

            // Assert
            expect($quota)->toHaveKey('type', QuotaExtension::TYPE_REQUESTS)
                ->and($quota)->toHaveKey('name', 'API Requests')
                ->and($quota)->toHaveKey('limit', 1_000)
                ->and($quota)->toHaveKey('used', 250)
                ->and($quota)->toHaveKey('remaining', 750)
                ->and($quota)->toHaveKey('period', QuotaExtension::PERIOD_MONTH)
                ->and($quota)->toHaveKey('unit', 'requests')
                ->and($quota)->toHaveKey('resets_at', $resetsAt->toIso8601String());
        });

        test('buildQuota calculates remaining correctly', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $quota = $extension->buildQuota('test', 'Test', 100, 35, 'month', 'units');

            // Assert
            expect($quota['remaining'])->toBe(65);
        });

        test('buildQuota handles zero remaining', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $quota = $extension->buildQuota('test', 'Test', 100, 100, 'day', 'units');

            // Assert
            expect($quota['remaining'])->toBe(0);
        });

        test('buildQuota prevents negative remaining', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $quota = $extension->buildQuota('test', 'Test', 100, 150, 'hour', 'units');

            // Assert
            expect($quota['remaining'])->toBe(0);
        });

        test('buildQuota omits resets_at when null', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $quota = $extension->buildQuota('test', 'Test', 100, 50, 'day', 'units');

            // Assert
            expect($quota)->not->toHaveKey('resets_at');
        });

        test('buildRequestsQuota creates requests quota entry', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $quota = $extension->buildRequestsQuota(5_000, 1_200);

            // Assert
            expect($quota)->toHaveKey('type', QuotaExtension::TYPE_REQUESTS)
                ->and($quota)->toHaveKey('name', 'API Requests')
                ->and($quota)->toHaveKey('limit', 5_000)
                ->and($quota)->toHaveKey('used', 1_200)
                ->and($quota)->toHaveKey('remaining', 3_800)
                ->and($quota)->toHaveKey('period', QuotaExtension::PERIOD_MONTH)
                ->and($quota)->toHaveKey('unit', 'requests');
        });

        test('buildComputeQuota creates compute quota entry', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $quota = $extension->buildComputeQuota(1_000_000, 250_000);

            // Assert
            expect($quota)->toHaveKey('type', QuotaExtension::TYPE_COMPUTE)
                ->and($quota)->toHaveKey('name', 'Compute Units')
                ->and($quota)->toHaveKey('limit', 1_000_000)
                ->and($quota)->toHaveKey('used', 250_000)
                ->and($quota)->toHaveKey('remaining', 750_000)
                ->and($quota)->toHaveKey('unit', 'tokens');
        });

        test('buildComputeQuota accepts custom unit', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $quota = $extension->buildComputeQuota(500, 100, 'credits');

            // Assert
            expect($quota['unit'])->toBe('credits');
        });

        test('buildStorageQuota creates storage quota entry', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $quota = $extension->buildStorageQuota(10_737_418_240, 5_368_709_120); // 10GB, 5GB in bytes

            // Assert
            expect($quota)->toHaveKey('type', QuotaExtension::TYPE_STORAGE)
                ->and($quota)->toHaveKey('name', 'File Storage')
                ->and($quota)->toHaveKey('limit', 10_737_418_240)
                ->and($quota)->toHaveKey('used', 5_368_709_120)
                ->and($quota)->toHaveKey('remaining', 5_368_709_120)
                ->and($quota)->toHaveKey('unit', 'bytes');
        });

        test('buildBandwidthQuota creates bandwidth quota entry', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $quota = $extension->buildBandwidthQuota(107_374_182_400, 21_474_836_480); // 100GB, 20GB

            // Assert
            expect($quota)->toHaveKey('type', QuotaExtension::TYPE_BANDWIDTH)
                ->and($quota)->toHaveKey('name', 'Monthly Transfer')
                ->and($quota)->toHaveKey('limit', 107_374_182_400)
                ->and($quota)->toHaveKey('used', 21_474_836_480)
                ->and($quota)->toHaveKey('unit', 'bytes');
        });

        test('buildCustomQuota creates custom quota entry', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $quota = $extension->buildCustomQuota('Webhook Calls', 1_000, 500, 'calls');

            // Assert
            expect($quota)->toHaveKey('type', QuotaExtension::TYPE_CUSTOM)
                ->and($quota)->toHaveKey('name', 'Webhook Calls')
                ->and($quota)->toHaveKey('limit', 1_000)
                ->and($quota)->toHaveKey('used', 500)
                ->and($quota)->toHaveKey('remaining', 500)
                ->and($quota)->toHaveKey('unit', 'calls');
        });

        test('enrichResponse adds quota extension to response', function (): void {
            // Arrange
            $extension = new QuotaExtension();
            $response = ResponseData::success(['data' => 'test'], '01JFEX0001');
            $quotas = [
                $extension->buildRequestsQuota(1_000, 100),
                $extension->buildComputeQuota(50_000, 10_000),
            ];

            // Act
            $enriched = $extension->enrichResponse($response, $quotas);

            // Assert
            expect($enriched)->toBeInstanceOf(ResponseData::class)
                ->and($enriched->extensions)->toHaveCount(1);

            $ext = $enriched->extensions[0];
            expect($ext->urn)->toBe(ExtensionUrn::Quota->value)
                ->and($ext->data)->toHaveKey('quotas', $quotas);
        });

        test('isNearLimit returns true when at threshold', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $result = $extension->isNearLimit(100, 80);

            // Assert
            expect($result)->toBeTrue();
        });

        test('isNearLimit returns true when above threshold', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $result = $extension->isNearLimit(100, 95);

            // Assert
            expect($result)->toBeTrue();
        });

        test('isNearLimit returns false when below threshold', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $result = $extension->isNearLimit(100, 50);

            // Assert
            expect($result)->toBeFalse();
        });

        test('isNearLimit accepts custom threshold', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $result = $extension->isNearLimit(100, 90, 0.9);

            // Assert
            expect($result)->toBeTrue();
        });

        test('isExceeded returns true when limit reached', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $result = $extension->isExceeded(100, 100);

            // Assert
            expect($result)->toBeTrue();
        });

        test('isExceeded returns true when limit exceeded', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $result = $extension->isExceeded(100, 150);

            // Assert
            expect($result)->toBeTrue();
        });

        test('isExceeded returns false when below limit', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $result = $extension->isExceeded(100, 99);

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('getIncludedTypes handles null options', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $types = $extension->getIncludedTypes(null);

            // Assert
            expect($types)->toBeNull();
        });

        test('buildQuota handles zero limit', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $quota = $extension->buildQuota('test', 'Test', 0, 0, 'day', 'units');

            // Assert
            expect($quota['limit'])->toBe(0)
                ->and($quota['used'])->toBe(0)
                ->and($quota['remaining'])->toBe(0);
        });

        test('buildRequestsQuota with custom period', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $quota = $extension->buildRequestsQuota(
                limit: 1_000,
                used: 500,
                period: QuotaExtension::PERIOD_DAY,
            );

            // Assert
            expect($quota['period'])->toBe(QuotaExtension::PERIOD_DAY);
        });

        test('buildComputeQuota with custom period', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $quota = $extension->buildComputeQuota(
                limit: 10_000,
                used: 2_000,
                unit: 'tokens',
                period: QuotaExtension::PERIOD_HOUR,
            );

            // Assert
            expect($quota['period'])->toBe(QuotaExtension::PERIOD_HOUR);
        });

        test('buildStorageQuota with billing cycle period', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $quota = $extension->buildStorageQuota(1_000_000, 500_000);

            // Assert
            expect($quota['period'])->toBe(QuotaExtension::PERIOD_BILLING_CYCLE);
        });

        test('enrichResponse appends to existing extensions', function (): void {
            // Arrange
            $extension = new QuotaExtension();
            $existingExt = ExtensionData::response('urn:cline:forrst:ext:other', ['data' => 'test']);
            $response = new ResponseData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0002',
                result: ['ok' => true],
                extensions: [$existingExt],
            );

            // Act
            $enriched = $extension->enrichResponse($response, []);

            // Assert
            expect($enriched->extensions)->toHaveCount(2);
        });

        test('enrichResponse with empty quotas array', function (): void {
            // Arrange
            $extension = new QuotaExtension();
            $response = ResponseData::success(['data' => 'test'], '01JFEX0003');

            // Act
            $enriched = $extension->enrichResponse($response, []);

            // Assert
            $ext = $enriched->extensions[0];
            expect($ext->data['quotas'])->toBe([]);
        });

        test('isNearLimit returns false for zero limit', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $result = $extension->isNearLimit(0, 0);

            // Assert
            expect($result)->toBeFalse();
        });

        test('isNearLimit returns false for negative limit', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $result = $extension->isNearLimit(-100, 50);

            // Assert
            expect($result)->toBeFalse();
        });

        test('isExceeded handles zero limit', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $result = $extension->isExceeded(0, 1);

            // Assert
            expect($result)->toBeTrue();
        });

        test('buildQuota with all quota types', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act & Assert
            $requests = $extension->buildQuota(QuotaExtension::TYPE_REQUESTS, 'Requests', 100, 50, 'month', 'requests');
            expect($requests['type'])->toBe(QuotaExtension::TYPE_REQUESTS);

            $compute = $extension->buildQuota(QuotaExtension::TYPE_COMPUTE, 'Compute', 1_000, 200, 'month', 'tokens');
            expect($compute['type'])->toBe(QuotaExtension::TYPE_COMPUTE);

            $storage = $extension->buildQuota(QuotaExtension::TYPE_STORAGE, 'Storage', 10_000, 5_000, 'billing_cycle', 'bytes');
            expect($storage['type'])->toBe(QuotaExtension::TYPE_STORAGE);

            $bandwidth = $extension->buildQuota(QuotaExtension::TYPE_BANDWIDTH, 'Bandwidth', 50_000, 10_000, 'month', 'bytes');
            expect($bandwidth['type'])->toBe(QuotaExtension::TYPE_BANDWIDTH);

            $custom = $extension->buildQuota(QuotaExtension::TYPE_CUSTOM, 'Custom', 500, 100, 'day', 'units');
            expect($custom['type'])->toBe(QuotaExtension::TYPE_CUSTOM);
        });

        test('buildQuota with all period types', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act & Assert
            $minute = $extension->buildQuota('test', 'Test', 100, 50, QuotaExtension::PERIOD_MINUTE, 'units');
            expect($minute['period'])->toBe(QuotaExtension::PERIOD_MINUTE);

            $hour = $extension->buildQuota('test', 'Test', 100, 50, QuotaExtension::PERIOD_HOUR, 'units');
            expect($hour['period'])->toBe(QuotaExtension::PERIOD_HOUR);

            $day = $extension->buildQuota('test', 'Test', 100, 50, QuotaExtension::PERIOD_DAY, 'units');
            expect($day['period'])->toBe(QuotaExtension::PERIOD_DAY);

            $month = $extension->buildQuota('test', 'Test', 100, 50, QuotaExtension::PERIOD_MONTH, 'units');
            expect($month['period'])->toBe(QuotaExtension::PERIOD_MONTH);

            $billing = $extension->buildQuota('test', 'Test', 100, 50, QuotaExtension::PERIOD_BILLING_CYCLE, 'units');
            expect($billing['period'])->toBe(QuotaExtension::PERIOD_BILLING_CYCLE);
        });

        test('isNearLimit with various thresholds', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act & Assert - 50% threshold
            expect($extension->isNearLimit(100, 50, 0.5))->toBeTrue();
            expect($extension->isNearLimit(100, 49, 0.5))->toBeFalse();

            // 90% threshold
            expect($extension->isNearLimit(100, 90, 0.9))->toBeTrue();
            expect($extension->isNearLimit(100, 89, 0.9))->toBeFalse();

            // 100% threshold
            expect($extension->isNearLimit(100, 100, 1.0))->toBeTrue();
            expect($extension->isNearLimit(100, 99, 1.0))->toBeFalse();
        });
    });

    describe('Sad Paths', function (): void {
        test('buildQuota handles usage exceeding limit', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $quota = $extension->buildQuota('test', 'Test', 100, 250, 'month', 'units');

            // Assert
            expect($quota['used'])->toBe(250)
                ->and($quota['limit'])->toBe(100)
                ->and($quota['remaining'])->toBe(0); // Prevented from going negative
        });

        test('isExceeded detects over-quota usage', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $result = $extension->isExceeded(100, 200);

            // Assert
            expect($result)->toBeTrue();
        });

        test('isNearLimit with exactly at threshold', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $result = $extension->isNearLimit(1_000, 800, 0.8);

            // Assert
            expect($result)->toBeTrue();
        });

        test('buildRequestsQuota at exactly limit', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $quota = $extension->buildRequestsQuota(5_000, 5_000);

            // Assert
            expect($quota['remaining'])->toBe(0)
                ->and($extension->isExceeded($quota['limit'], $quota['used']))->toBeTrue();
        });

        test('buildComputeQuota shows over-quota state', function (): void {
            // Arrange
            $extension = new QuotaExtension();

            // Act
            $quota = $extension->buildComputeQuota(10_000, 15_000);

            // Assert
            expect($quota['remaining'])->toBe(0)
                ->and($quota['used'])->toBeGreaterThan($quota['limit']);
        });

        test('enrichResponse with multiple quota types showing various states', function (): void {
            // Arrange
            $extension = new QuotaExtension();
            $response = ResponseData::success(['data' => 'test'], '01JFEX0004');

            $quotas = [
                $extension->buildRequestsQuota(1_000, 100),    // 10% used
                $extension->buildComputeQuota(10_000, 8_500),   // 85% used
                $extension->buildStorageQuota(1_000_000, 1_000_000), // 100% used
                $extension->buildBandwidthQuota(50_000, 60_000), // Over quota
            ];

            // Act
            $enriched = $extension->enrichResponse($response, $quotas);

            // Assert
            $ext = $enriched->extensions[0];
            expect($ext->data['quotas'])->toHaveCount(4)
                ->and($ext->data['quotas'][0]['remaining'])->toBe(900)
                ->and($ext->data['quotas'][1]['remaining'])->toBe(1_500)
                ->and($ext->data['quotas'][2]['remaining'])->toBe(0)
                ->and($ext->data['quotas'][3]['remaining'])->toBe(0);
        });
    });
});

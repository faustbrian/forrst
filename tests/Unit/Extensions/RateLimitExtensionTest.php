<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Extensions\ExtensionUrn;
use Cline\Forrst\Extensions\RateLimitExtension;

describe('RateLimitExtension', function (): void {
    describe('Happy Paths', function (): void {
        test('getUrn returns correct URN constant', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $urn = $extension->getUrn();

            // Assert
            expect($urn)->toBe(ExtensionUrn::RateLimit->value)
                ->and($urn)->toBe('urn:cline:forrst:ext:rate-limit');
        });

        test('isErrorFatal returns false for advisory rate limit info', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $result = $extension->isErrorFatal();

            // Assert
            expect($result)->toBeFalse();
        });

        test('getRequestedScope extracts scope from options', function (): void {
            // Arrange
            $extension = new RateLimitExtension();
            $options = ['scope' => 'service'];

            // Act
            $scope = $extension->getRequestedScope($options);

            // Assert
            expect($scope)->toBe('service');
        });

        test('getRequestedScope returns null when not specified', function (): void {
            // Arrange
            $extension = new RateLimitExtension();
            $options = [];

            // Act
            $scope = $extension->getRequestedScope($options);

            // Assert
            expect($scope)->toBeNull();
        });

        test('buildDuration creates duration object', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $duration = $extension->buildDuration(1, 'minute');

            // Assert
            expect($duration)->toBe([
                'value' => 1,
                'unit' => 'minute',
            ]);
        });

        test('buildRateLimit creates complete rate limit entry', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $rateLimit = $extension->buildRateLimit(
                limit: 1_000,
                used: 42,
                windowValue: 1,
                windowUnit: RateLimitExtension::UNIT_MINUTE,
                resetsIn: 47,
                scope: RateLimitExtension::SCOPE_SERVICE,
            );

            // Assert
            expect($rateLimit)->toHaveKey('limit', 1_000)
                ->and($rateLimit)->toHaveKey('used', 42)
                ->and($rateLimit)->toHaveKey('remaining', 958)
                ->and($rateLimit)->toHaveKey('window', ['value' => 1, 'unit' => 'minute'])
                ->and($rateLimit)->toHaveKey('resets_in', ['value' => 47, 'unit' => 'second'])
                ->and($rateLimit)->toHaveKey('scope', 'service')
                ->and($rateLimit)->not->toHaveKey('warning');
        });

        test('buildRateLimit includes custom warning', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $rateLimit = $extension->buildRateLimit(
                limit: 100,
                used: 95,
                windowValue: 1,
                windowUnit: RateLimitExtension::UNIT_MINUTE,
                resetsIn: 30,
                scope: RateLimitExtension::SCOPE_FUNCTION,
                warning: 'Custom warning message',
            );

            // Assert
            expect($rateLimit)->toHaveKey('warning', 'Custom warning message');
        });

        test('buildRateLimit adds automatic warning when near limit', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $rateLimit = $extension->buildRateLimit(
                limit: 100,
                used: 95,
                windowValue: 1,
                windowUnit: RateLimitExtension::UNIT_MINUTE,
                resetsIn: 30,
                scope: RateLimitExtension::SCOPE_USER,
            );

            // Assert
            expect($rateLimit)->toHaveKey('warning', 'Rate limit nearly exhausted');
        });

        test('buildMultiScopeData creates scopes wrapper', function (): void {
            // Arrange
            $extension = new RateLimitExtension();
            $globalScope = $extension->buildScopeEntry(10_000, 4_523, 1, 'minute', 32);
            $serviceScope = $extension->buildScopeEntry(1_000, 153, 1, 'minute', 32);

            // Act
            $data = $extension->buildMultiScopeData([
                'global' => $globalScope,
                'service' => $serviceScope,
            ]);

            // Assert
            expect($data)->toHaveKey('scopes')
                ->and($data['scopes'])->toHaveKey('global')
                ->and($data['scopes'])->toHaveKey('service')
                ->and($data['scopes']['global']['remaining'])->toBe(5_477)
                ->and($data['scopes']['service']['remaining'])->toBe(847);
        });

        test('buildScopeEntry creates entry without scope field', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $entry = $extension->buildScopeEntry(1_000, 500, 1, 'minute', 45);

            // Assert
            expect($entry)->toHaveKey('limit', 1_000)
                ->and($entry)->toHaveKey('used', 500)
                ->and($entry)->toHaveKey('remaining', 500)
                ->and($entry)->toHaveKey('window')
                ->and($entry)->toHaveKey('resets_in')
                ->and($entry)->not->toHaveKey('scope');
        });

        test('enrichResponse adds rate limit extension to response', function (): void {
            // Arrange
            $extension = new RateLimitExtension();
            $response = ResponseData::success(['order_id' => 456], '01JFEX0001');
            $rateLimit = $extension->buildRateLimit(1_000, 42, 1, 'minute', 47, 'service');

            // Act
            $enriched = $extension->enrichResponse($response, $rateLimit);

            // Assert
            expect($enriched)->toBeInstanceOf(ResponseData::class)
                ->and($enriched->extensions)->toHaveCount(1);

            $ext = $enriched->extensions[0];
            expect($ext->urn)->toBe(ExtensionUrn::RateLimit->value)
                ->and($ext->data)->toHaveKey('limit', 1_000)
                ->and($ext->data)->toHaveKey('remaining', 958);
        });

        test('isNearLimit returns true when remaining is at threshold', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act - 10% remaining = at default 10% threshold
            $result = $extension->isNearLimit(100, 90);

            // Assert
            expect($result)->toBeTrue();
        });

        test('isNearLimit returns true when remaining is below threshold', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act - 5% remaining
            $result = $extension->isNearLimit(100, 95);

            // Assert
            expect($result)->toBeTrue();
        });

        test('isNearLimit returns false when above threshold', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act - 50% remaining
            $result = $extension->isNearLimit(100, 50);

            // Assert
            expect($result)->toBeFalse();
        });

        test('isNearLimit accepts custom threshold', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act - 20% remaining with 20% threshold
            $result = $extension->isNearLimit(100, 80, 0.2);

            // Assert
            expect($result)->toBeTrue();
        });

        test('isExceeded returns true when limit reached', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $result = $extension->isExceeded(100, 100);

            // Assert
            expect($result)->toBeTrue();
        });

        test('isExceeded returns true when limit exceeded', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $result = $extension->isExceeded(100, 150);

            // Assert
            expect($result)->toBeTrue();
        });

        test('isExceeded returns false when below limit', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $result = $extension->isExceeded(100, 99);

            // Assert
            expect($result)->toBeFalse();
        });

        test('calculateRemaining returns correct value', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $remaining = $extension->calculateRemaining(1_000, 350);

            // Assert
            expect($remaining)->toBe(650);
        });
    });

    describe('Edge Cases', function (): void {
        test('getRequestedScope handles null options', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $scope = $extension->getRequestedScope(null);

            // Assert
            expect($scope)->toBeNull();
        });

        test('buildRateLimit handles zero remaining', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $rateLimit = $extension->buildRateLimit(100, 100, 1, 'minute', 30, 'service');

            // Assert
            expect($rateLimit['remaining'])->toBe(0);
        });

        test('buildRateLimit prevents negative remaining', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $rateLimit = $extension->buildRateLimit(100, 150, 1, 'minute', 30, 'service');

            // Assert
            expect($rateLimit['remaining'])->toBe(0);
        });

        test('buildDuration with various time units', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act & Assert
            expect($extension->buildDuration(30, RateLimitExtension::UNIT_SECOND))
                ->toBe(['value' => 30, 'unit' => 'second']);

            expect($extension->buildDuration(5, RateLimitExtension::UNIT_MINUTE))
                ->toBe(['value' => 5, 'unit' => 'minute']);

            expect($extension->buildDuration(1, RateLimitExtension::UNIT_HOUR))
                ->toBe(['value' => 1, 'unit' => 'hour']);
        });

        test('buildRateLimit with all scope types', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act & Assert
            $global = $extension->buildRateLimit(10_000, 5_000, 1, 'minute', 30, RateLimitExtension::SCOPE_GLOBAL);
            expect($global['scope'])->toBe('global');

            $service = $extension->buildRateLimit(1_000, 500, 1, 'minute', 30, RateLimitExtension::SCOPE_SERVICE);
            expect($service['scope'])->toBe('service');

            $function = $extension->buildRateLimit(100, 50, 1, 'minute', 30, RateLimitExtension::SCOPE_FUNCTION);
            expect($function['scope'])->toBe('function');

            $user = $extension->buildRateLimit(500, 250, 1, 'minute', 30, RateLimitExtension::SCOPE_USER);
            expect($user['scope'])->toBe('user');
        });

        test('enrichResponse appends to existing extensions', function (): void {
            // Arrange
            $extension = new RateLimitExtension();
            $existingExt = ExtensionData::response('urn:cline:forrst:ext:other', ['data' => 'test']);
            $response = new ResponseData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0002',
                result: ['ok' => true],
                extensions: [$existingExt],
            );
            $rateLimit = $extension->buildRateLimit(100, 50, 1, 'minute', 30, 'service');

            // Act
            $enriched = $extension->enrichResponse($response, $rateLimit);

            // Assert
            expect($enriched->extensions)->toHaveCount(2);
        });

        test('enrichResponse with multi-scope data', function (): void {
            // Arrange
            $extension = new RateLimitExtension();
            $response = ResponseData::success(['success' => true], '01JFEX0003');
            $multiScope = $extension->buildMultiScopeData([
                'global' => $extension->buildScopeEntry(10_000, 4_523, 1, 'minute', 32),
                'service' => $extension->buildScopeEntry(1_000, 153, 1, 'minute', 32),
                'function' => $extension->buildScopeEntry(100, 45, 1, 'minute', 32),
            ]);

            // Act
            $enriched = $extension->enrichResponse($response, $multiScope);

            // Assert
            $ext = $enriched->extensions[0];
            expect($ext->data)->toHaveKey('scopes')
                ->and($ext->data['scopes'])->toHaveCount(3);
        });

        test('isNearLimit returns false for zero limit', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $result = $extension->isNearLimit(0, 0);

            // Assert
            expect($result)->toBeFalse();
        });

        test('isNearLimit returns false for negative limit', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $result = $extension->isNearLimit(-100, 50);

            // Assert
            expect($result)->toBeFalse();
        });

        test('calculateRemaining returns zero when limit exceeded', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $remaining = $extension->calculateRemaining(100, 150);

            // Assert
            expect($remaining)->toBe(0);
        });

        test('calculateRemaining returns zero when exactly at limit', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $remaining = $extension->calculateRemaining(100, 100);

            // Assert
            expect($remaining)->toBe(0);
        });

        test('isNearLimit with various custom thresholds', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act & Assert - 5% threshold
            expect($extension->isNearLimit(100, 95, 0.05))->toBeTrue();
            expect($extension->isNearLimit(100, 94, 0.05))->toBeFalse();

            // 25% threshold
            expect($extension->isNearLimit(100, 75, 0.25))->toBeTrue();
            expect($extension->isNearLimit(100, 74, 0.25))->toBeFalse();
        });
    });

    describe('Sad Paths', function (): void {
        test('buildRateLimit handles usage exceeding limit', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $rateLimit = $extension->buildRateLimit(100, 250, 1, 'minute', 0, 'service');

            // Assert
            expect($rateLimit['used'])->toBe(250)
                ->and($rateLimit['limit'])->toBe(100)
                ->and($rateLimit['remaining'])->toBe(0);
        });

        test('isExceeded detects over-limit usage', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $result = $extension->isExceeded(100, 200);

            // Assert
            expect($result)->toBeTrue();
        });

        test('buildRateLimit at exactly limit with warning', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $rateLimit = $extension->buildRateLimit(100, 100, 1, 'minute', 30, 'service');

            // Assert
            expect($rateLimit['remaining'])->toBe(0)
                ->and($rateLimit)->toHaveKey('warning', 'Rate limit nearly exhausted');
        });

        test('buildScopeEntry shows over-limit state', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $entry = $extension->buildScopeEntry(1_000, 1_500, 1, 'minute', 30);

            // Assert
            expect($entry['remaining'])->toBe(0)
                ->and($entry['used'])->toBeGreaterThan($entry['limit']);
        });

        test('enrichResponse with exhausted rate limit', function (): void {
            // Arrange
            $extension = new RateLimitExtension();
            $response = ResponseData::success(['data' => 'test'], '01JFEX0004');
            $rateLimit = $extension->buildRateLimit(100, 100, 1, 'minute', 23, 'function');

            // Act
            $enriched = $extension->enrichResponse($response, $rateLimit);

            // Assert
            $ext = $enriched->extensions[0];
            expect($ext->data['remaining'])->toBe(0)
                ->and($ext->data)->toHaveKey('warning');
        });

        test('multi-scope data with mixed exhaustion states', function (): void {
            // Arrange
            $extension = new RateLimitExtension();

            // Act
            $data = $extension->buildMultiScopeData([
                'global' => $extension->buildScopeEntry(10_000, 5_000, 1, 'minute', 32), // 50% used
                'service' => $extension->buildScopeEntry(1_000, 950, 1, 'minute', 32),  // 95% used
                'function' => $extension->buildScopeEntry(100, 100, 1, 'minute', 32),  // 100% used
            ]);

            // Assert
            expect($data['scopes']['global']['remaining'])->toBe(5_000)
                ->and($data['scopes']['service']['remaining'])->toBe(50)
                ->and($data['scopes']['function']['remaining'])->toBe(0);
        });
    });
});

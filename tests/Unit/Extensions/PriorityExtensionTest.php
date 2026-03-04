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
use Cline\Forrst\Extensions\PriorityExtension;

describe('PriorityExtension', function (): void {
    describe('Happy Paths', function (): void {
        test('getUrn returns correct URN constant', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $urn = $extension->getUrn();

            // Assert
            expect($urn)->toBe(ExtensionUrn::Priority->value)
                ->and($urn)->toBe('urn:cline:forrst:ext:priority');
        });

        test('getLevel returns requested priority level', function (): void {
            // Arrange
            $extension = new PriorityExtension();
            $options = ['level' => PriorityExtension::LEVEL_HIGH];

            // Act
            $level = $extension->getLevel($options);

            // Assert
            expect($level)->toBe(PriorityExtension::LEVEL_HIGH)
                ->and($level)->toBe('high');
        });

        test('getLevel returns normal when level not specified', function (): void {
            // Arrange
            $extension = new PriorityExtension();
            $options = [];

            // Act
            $level = $extension->getLevel($options);

            // Assert
            expect($level)->toBe(PriorityExtension::LEVEL_NORMAL);
        });

        test('getLevel returns normal for invalid level', function (): void {
            // Arrange
            $extension = new PriorityExtension();
            $options = ['level' => 'invalid'];

            // Act
            $level = $extension->getLevel($options);

            // Assert
            expect($level)->toBe(PriorityExtension::LEVEL_NORMAL);
        });

        test('getReason extracts reason from options', function (): void {
            // Arrange
            $extension = new PriorityExtension();
            $options = ['reason' => 'User-facing request'];

            // Act
            $reason = $extension->getReason($options);

            // Assert
            expect($reason)->toBe('User-facing request');
        });

        test('getReason returns null when not provided', function (): void {
            // Arrange
            $extension = new PriorityExtension();
            $options = [];

            // Act
            $reason = $extension->getReason($options);

            // Assert
            expect($reason)->toBeNull();
        });

        test('getLevelValue returns correct numeric values', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act & Assert
            expect($extension->getLevelValue(PriorityExtension::LEVEL_CRITICAL))->toBe(5);
            expect($extension->getLevelValue(PriorityExtension::LEVEL_HIGH))->toBe(4);
            expect($extension->getLevelValue(PriorityExtension::LEVEL_NORMAL))->toBe(3);
            expect($extension->getLevelValue(PriorityExtension::LEVEL_LOW))->toBe(2);
            expect($extension->getLevelValue(PriorityExtension::LEVEL_BULK))->toBe(1);
        });

        test('getLevelValue returns normal value for unknown level', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $value = $extension->getLevelValue('unknown');

            // Assert
            expect($value)->toBe(3); // NORMAL value
        });

        test('compareLevels returns negative when first is lower', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $result = $extension->compareLevels(
                PriorityExtension::LEVEL_LOW,
                PriorityExtension::LEVEL_HIGH,
            );

            // Assert
            expect($result)->toBeLessThan(0);
        });

        test('compareLevels returns positive when first is higher', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $result = $extension->compareLevels(
                PriorityExtension::LEVEL_CRITICAL,
                PriorityExtension::LEVEL_NORMAL,
            );

            // Assert
            expect($result)->toBeGreaterThan(0);
        });

        test('compareLevels returns zero when levels are equal', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $result = $extension->compareLevels(
                PriorityExtension::LEVEL_HIGH,
                PriorityExtension::LEVEL_HIGH,
            );

            // Assert
            expect($result)->toBe(0);
        });

        test('isHigherPriority returns true when first is higher', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $result = $extension->isHigherPriority(
                PriorityExtension::LEVEL_HIGH,
                PriorityExtension::LEVEL_NORMAL,
            );

            // Assert
            expect($result)->toBeTrue();
        });

        test('isHigherPriority returns false when first is lower', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $result = $extension->isHigherPriority(
                PriorityExtension::LEVEL_BULK,
                PriorityExtension::LEVEL_CRITICAL,
            );

            // Assert
            expect($result)->toBeFalse();
        });

        test('isHigherPriority returns false when levels are equal', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $result = $extension->isHigherPriority(
                PriorityExtension::LEVEL_NORMAL,
                PriorityExtension::LEVEL_NORMAL,
            );

            // Assert
            expect($result)->toBeFalse();
        });

        test('getValidLevels returns all priority levels', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $levels = $extension->getValidLevels();

            // Assert
            expect($levels)->toHaveCount(5)
                ->and($levels)->toContain('critical')
                ->and($levels)->toContain('high')
                ->and($levels)->toContain('normal')
                ->and($levels)->toContain('low')
                ->and($levels)->toContain('bulk');
        });

        test('enrichResponse adds priority extension to response', function (): void {
            // Arrange
            $extension = new PriorityExtension();
            $response = ResponseData::success(['data' => 'test'], '01JFEX0001');

            // Act
            $enriched = $extension->enrichResponse(
                $response,
                honored: true,
                effectiveLevel: PriorityExtension::LEVEL_HIGH,
            );

            // Assert
            expect($enriched)->toBeInstanceOf(ResponseData::class)
                ->and($enriched->extensions)->toHaveCount(1);

            $ext = $enriched->extensions[0];
            expect($ext->urn)->toBe(ExtensionUrn::Priority->value)
                ->and($ext->data)->toHaveKey('honored', true)
                ->and($ext->data)->toHaveKey('effective_level', 'high');
        });

        test('enrichResponse includes queue position when provided', function (): void {
            // Arrange
            $extension = new PriorityExtension();
            $response = ResponseData::success(['data' => 'test'], '01JFEX0002');

            // Act
            $enriched = $extension->enrichResponse(
                $response,
                honored: true,
                effectiveLevel: PriorityExtension::LEVEL_NORMAL,
                queuePosition: 5,
            );

            // Assert
            $ext = $enriched->extensions[0];
            expect($ext->data)->toHaveKey('queue_position', 5);
        });

        test('enrichResponse includes wait time when provided', function (): void {
            // Arrange
            $extension = new PriorityExtension();
            $response = ResponseData::success(['data' => 'test'], '01JFEX0003');
            $waitTime = ['value' => 250, 'unit' => 'millisecond'];

            // Act
            $enriched = $extension->enrichResponse(
                $response,
                honored: false,
                effectiveLevel: PriorityExtension::LEVEL_NORMAL,
                waitTime: $waitTime,
            );

            // Assert
            $ext = $enriched->extensions[0];
            expect($ext->data)->toHaveKey('wait_time', $waitTime);
        });

        test('buildResponseData creates complete response data structure', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $data = $extension->buildResponseData(
                honored: true,
                effectiveLevel: PriorityExtension::LEVEL_CRITICAL,
                queuePosition: 1,
                waitTimeMs: 100,
            );

            // Assert
            expect($data)->toHaveKey('honored', true)
                ->and($data)->toHaveKey('effective_level', 'critical')
                ->and($data)->toHaveKey('queue_position', 1)
                ->and($data)->toHaveKey('wait_time', ['value' => 100, 'unit' => 'millisecond']);
        });

        test('buildResponseData works without optional parameters', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $data = $extension->buildResponseData(
                honored: false,
                effectiveLevel: PriorityExtension::LEVEL_LOW,
            );

            // Assert
            expect($data)->toHaveKey('honored', false)
                ->and($data)->toHaveKey('effective_level', 'low')
                ->and($data)->not->toHaveKey('queue_position')
                ->and($data)->not->toHaveKey('wait_time');
        });
    });

    describe('Edge Cases', function (): void {
        test('getLevel handles null options', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $level = $extension->getLevel(null);

            // Assert
            expect($level)->toBe(PriorityExtension::LEVEL_NORMAL);
        });

        test('getReason handles null options', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $reason = $extension->getReason(null);

            // Assert
            expect($reason)->toBeNull();
        });

        test('enrichResponse preserves existing response data', function (): void {
            // Arrange
            $extension = new PriorityExtension();
            $response = ResponseData::success(['user_id' => 123, 'name' => 'Test'], '01JFEX0004');

            // Act
            $enriched = $extension->enrichResponse(
                $response,
                honored: true,
                effectiveLevel: PriorityExtension::LEVEL_HIGH,
            );

            // Assert
            expect($enriched->result)->toBe(['user_id' => 123, 'name' => 'Test'])
                ->and($enriched->id)->toBe('01JFEX0004');
        });

        test('enrichResponse appends to existing extensions', function (): void {
            // Arrange
            $extension = new PriorityExtension();
            $existingExt = ExtensionData::response('urn:cline:forrst:ext:other', ['data' => 'test']);
            $response = new ResponseData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0005',
                result: ['ok' => true],
                extensions: [$existingExt],
            );

            // Act
            $enriched = $extension->enrichResponse(
                $response,
                honored: true,
                effectiveLevel: PriorityExtension::LEVEL_NORMAL,
            );

            // Assert
            expect($enriched->extensions)->toHaveCount(2)
                ->and($enriched->extensions[0]->urn)->toBe('urn:cline:forrst:ext:other')
                ->and($enriched->extensions[1]->urn)->toBe(ExtensionUrn::Priority->value);
        });

        test('buildResponseData with zero queue position', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $data = $extension->buildResponseData(
                honored: true,
                effectiveLevel: PriorityExtension::LEVEL_CRITICAL,
                queuePosition: 0,
            );

            // Assert
            expect($data)->toHaveKey('queue_position', 0);
        });

        test('buildResponseData with zero wait time', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $data = $extension->buildResponseData(
                honored: true,
                effectiveLevel: PriorityExtension::LEVEL_HIGH,
                waitTimeMs: 0,
            );

            // Assert
            expect($data)->toHaveKey('wait_time', ['value' => 0, 'unit' => 'millisecond']);
        });

        test('compareLevels handles unknown levels as normal priority', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $result1 = $extension->compareLevels('unknown', PriorityExtension::LEVEL_NORMAL);
            $result2 = $extension->compareLevels(PriorityExtension::LEVEL_NORMAL, 'unknown');

            // Assert
            expect($result1)->toBe(0)
                ->and($result2)->toBe(0);
        });

        test('isHigherPriority handles unknown levels', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $result = $extension->isHigherPriority('unknown', PriorityExtension::LEVEL_LOW);

            // Assert
            expect($result)->toBeTrue(); // unknown = normal (3) > low (2)
        });

        test('getValidLevels returns keys in correct order', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $levels = $extension->getValidLevels();

            // Assert
            expect($levels)->toEqual([
                'critical',
                'high',
                'normal',
                'low',
                'bulk',
            ]);
        });
    });

    describe('Sad Paths', function (): void {
        test('enrichResponse with honored false indicates priority was not applied', function (): void {
            // Arrange
            $extension = new PriorityExtension();
            $response = ResponseData::success(['data' => 'test'], '01JFEX0006');

            // Act
            $enriched = $extension->enrichResponse(
                $response,
                honored: false,
                effectiveLevel: PriorityExtension::LEVEL_NORMAL,
            );

            // Assert
            $ext = $enriched->extensions[0];
            expect($ext->data['honored'])->toBeFalse()
                ->and($ext->data['effective_level'])->toBe('normal');
        });

        test('enrichResponse shows effective level different from requested', function (): void {
            // Arrange
            $extension = new PriorityExtension();
            $response = ResponseData::success(['data' => 'test'], '01JFEX0007');

            // Act - Requested critical but got normal
            $enriched = $extension->enrichResponse(
                $response,
                honored: false,
                effectiveLevel: PriorityExtension::LEVEL_NORMAL,
            );

            // Assert
            $ext = $enriched->extensions[0];
            expect($ext->data['honored'])->toBeFalse()
                ->and($ext->data['effective_level'])->toBe('normal');
        });

        test('buildResponseData with very large queue position', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $data = $extension->buildResponseData(
                honored: true,
                effectiveLevel: PriorityExtension::LEVEL_BULK,
                queuePosition: 9_999,
            );

            // Assert
            expect($data['queue_position'])->toBe(9_999);
        });

        test('buildResponseData with very large wait time', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act
            $data = $extension->buildResponseData(
                honored: true,
                effectiveLevel: PriorityExtension::LEVEL_LOW,
                waitTimeMs: 999_999,
            );

            // Assert
            expect($data['wait_time'])->toBe(['value' => 999_999, 'unit' => 'millisecond']);
        });

        test('compareLevels demonstrates priority ordering', function (): void {
            // Arrange
            $extension = new PriorityExtension();

            // Act & Assert - Critical is highest
            expect($extension->compareLevels(
                PriorityExtension::LEVEL_CRITICAL,
                PriorityExtension::LEVEL_HIGH,
            ))->toBeGreaterThan(0);

            expect($extension->compareLevels(
                PriorityExtension::LEVEL_CRITICAL,
                PriorityExtension::LEVEL_BULK,
            ))->toBeGreaterThan(0);

            // Bulk is lowest
            expect($extension->compareLevels(
                PriorityExtension::LEVEL_BULK,
                PriorityExtension::LEVEL_LOW,
            ))->toBeLessThan(0);

            expect($extension->compareLevels(
                PriorityExtension::LEVEL_BULK,
                PriorityExtension::LEVEL_CRITICAL,
            ))->toBeLessThan(0);
        });
    });
});

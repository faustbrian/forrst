<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\Forrst\Discovery\ResultDescriptorData;
use Cline\Forrst\Extensions\Diagnostics\Functions\PingFunction;
use Illuminate\Support\Sleep;

describe('PingFunction', function (): void {
    describe('Happy Paths', function (): void {
        describe('getName()', function (): void {
            test('returns standard Forrst ping function name', function (): void {
                // Arrange
                $function = new PingFunction();

                // Act
                $result = $function->getUrn();

                // Assert
                expect($result)->toBe('urn:cline:forrst:ext:diagnostics:fn:ping');
            });
        });

        describe('getVersion()', function (): void {
            test('returns default version', function (): void {
                // Arrange
                $function = new PingFunction();

                // Act
                $result = $function->getVersion();

                // Assert
                expect($result)->toBe('1.0.0');
            });
        });

        describe('getSummary()', function (): void {
            test('returns description of ping function', function (): void {
                // Arrange
                $function = new PingFunction();

                // Act
                $result = $function->getSummary();

                // Assert
                expect($result)->toBe('Simple connectivity check');
            });
        });

        describe('getArguments()', function (): void {
            test('returns empty array since ping requires no arguments', function (): void {
                // Arrange
                $function = new PingFunction();

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toHaveCount(0);
            });
        });

        describe('getResult()', function (): void {
            test('returns ResultDescriptorData with ping response schema', function (): void {
                // Arrange
                $function = new PingFunction();

                // Act
                $result = $function->getResult();

                // Assert
                expect($result)->toBeInstanceOf(ResultDescriptorData::class);

                $array = $result->toArray();
                expect($array)->toHaveKey('schema')
                    ->and($array['schema'])->toHaveKey('type')
                    ->and($array['schema']['type'])->toBe('object')
                    ->and($array)->toHaveKey('description')
                    ->and($array['description'])->toBe('Ping response with health status');
            });

            test('schema defines status enum with health states', function (): void {
                // Arrange
                $function = new PingFunction();

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['schema']['properties']['status'])->toHaveKey('enum')
                    ->and($array['schema']['properties']['status']['enum'])
                    ->toContain('healthy')
                    ->and($array['schema']['properties']['status']['enum'])
                    ->toContain('degraded')
                    ->and($array['schema']['properties']['status']['enum'])
                    ->toContain('unhealthy');
            });

            test('schema defines timestamp as ISO 8601 date-time', function (): void {
                // Arrange
                $function = new PingFunction();

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['schema']['properties']['timestamp'])->toHaveKey('format')
                    ->and($array['schema']['properties']['timestamp']['format'])->toBe('date-time');
            });

            test('schema requires status and timestamp fields', function (): void {
                // Arrange
                $function = new PingFunction();

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['schema'])->toHaveKey('required')
                    ->and($array['schema']['required'])->toContain('status')
                    ->and($array['schema']['required'])->toContain('timestamp');
            });

            test('schema defines optional details object', function (): void {
                // Arrange
                $function = new PingFunction();

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['schema']['properties'])->toHaveKey('details')
                    ->and($array['schema']['properties']['details']['type'])->toBe('object');
            });
        });

        describe('__invoke()', function (): void {
            test('returns healthy status', function (): void {
                // Arrange
                $function = new PingFunction();

                // Act
                $result = $function();

                // Assert
                expect($result)->toHaveKey('status')
                    ->and($result['status'])->toBe('healthy');
            });

            test('returns current timestamp in ISO 8601 format', function (): void {
                // Arrange
                $function = new PingFunction();
                $before = CarbonImmutable::now()->subSecond();

                // Act
                $result = $function();
                $after = CarbonImmutable::now()->addSecond();

                // Assert
                expect($result)->toHaveKey('timestamp');
                $timestamp = CarbonImmutable::parse($result['timestamp']);
                expect($timestamp->between($before, $after))->toBeTrue();
            });

            test('returns only status and timestamp fields', function (): void {
                // Arrange
                $function = new PingFunction();

                // Act
                $result = $function();

                // Assert
                expect($result)->toHaveKeys(['status', 'timestamp'])
                    ->and(count($result))->toBe(2);
            });

            test('timestamp is valid ISO 8601 string', function (): void {
                // Arrange
                $function = new PingFunction();

                // Act
                $result = $function();

                // Assert
                expect($result['timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
            });
        });
    });

    describe('Edge Cases', function (): void {
        describe('__invoke() consistency', function (): void {
            test('returns consistent structure on multiple invocations', function (): void {
                // Arrange
                $function = new PingFunction();

                // Act
                $result1 = $function();
                $result2 = $function();

                // Assert
                expect($result1)->toHaveKeys(['status', 'timestamp'])
                    ->and($result2)->toHaveKeys(['status', 'timestamp'])
                    ->and($result1['status'])->toBe($result2['status'])
                    ->and($result1['status'])->toBe('healthy');
            });

            test('timestamps are different on sequential calls', function (): void {
                // Arrange
                $function = new PingFunction();

                // Act
                $result1 = $function();
                Sleep::sleep(1); // 1 second delay to ensure different timestamps
                $result2 = $function();

                // Assert
                expect($result1['timestamp'])->not()->toBe($result2['timestamp']);
            });
        });
    });

    describe('Sad Paths', function (): void {
        // Note: PingFunction has no sad paths as it always returns healthy status
        // and requires no input arguments that could be invalid
        test('function always succeeds with valid response', function (): void {
            // Arrange
            $function = new PingFunction();

            // Act
            $result = $function();

            // Assert - should never throw exception
            expect($result)->toBeArray()
                ->and($result['status'])->toBe('healthy');
        });
    });
});

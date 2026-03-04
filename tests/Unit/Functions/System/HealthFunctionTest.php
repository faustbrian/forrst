<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\Forrst\Contracts\HealthCheckerInterface;
use Cline\Forrst\Data\HealthStatus;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Discovery\ArgumentData;
use Cline\Forrst\Discovery\ResultDescriptorData;
use Cline\Forrst\Exceptions\InvalidFieldValueException;
use Cline\Forrst\Extensions\Diagnostics\Functions\HealthFunction;
use Mockery\MockInterface;

describe('HealthFunction', function (): void {
    describe('Happy Paths', function (): void {
        describe('getName()', function (): void {
            test('returns standard Forrst health function name', function (): void {
                // Arrange
                $function = new HealthFunction(requireAuthForDetails: false);

                // Act
                $result = $function->getUrn();

                // Assert
                expect($result)->toBe('urn:cline:forrst:ext:diagnostics:fn:health');
            });
        });

        describe('getVersion()', function (): void {
            test('returns default version', function (): void {
                // Arrange
                $function = new HealthFunction(requireAuthForDetails: false);

                // Act
                $result = $function->getVersion();

                // Assert
                expect($result)->toBe('1.0.0');
            });
        });

        describe('getSummary()', function (): void {
            test('returns description of health function', function (): void {
                // Arrange
                $function = new HealthFunction(requireAuthForDetails: false);

                // Act
                $result = $function->getSummary();

                // Assert
                expect($result)->toBe('Comprehensive health check with component-level status');
            });
        });

        describe('getArguments()', function (): void {
            test('returns component and include_details arguments', function (): void {
                // Arrange
                $function = new HealthFunction(requireAuthForDetails: false);

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toHaveCount(2);
            });

            test('component argument is optional string', function (): void {
                // Arrange
                $function = new HealthFunction(requireAuthForDetails: false);

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result[0])->toBeInstanceOf(ArgumentData::class)
                    ->and($result[0]->name)->toBe('component')
                    ->and($result[0]->schema['type'])->toBe('string')
                    ->and($result[0]->required)->toBeFalse()
                    ->and($result[0]->description)->toBe('Check specific component only (use "self" for basic ping)');
            });

            test('include_details argument is optional boolean with default true', function (): void {
                // Arrange
                $function = new HealthFunction(requireAuthForDetails: false);

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result[1])->toBeInstanceOf(ArgumentData::class)
                    ->and($result[1]->name)->toBe('include_details')
                    ->and($result[1]->schema['type'])->toBe('boolean')
                    ->and($result[1]->required)->toBeFalse()
                    ->and($result[1]->schema)->toHaveKey('default')
                    ->and($result[1]->schema['default'])->toBeTrue();
            });
        });

        describe('getResult()', function (): void {
            test('returns ResultDescriptorData with health response schema', function (): void {
                // Arrange
                $function = new HealthFunction(requireAuthForDetails: false);

                // Act
                $result = $function->getResult();

                // Assert
                expect($result)->toBeInstanceOf(ResultDescriptorData::class);

                $array = $result->toArray();
                expect($array)->toHaveKey('schema')
                    ->and($array['schema'])->toHaveKey('type')
                    ->and($array['schema']['type'])->toBe('object')
                    ->and($array)->toHaveKey('description')
                    ->and($array['description'])->toBe('Health check response with component status');
            });

            test('schema defines status enum with health states', function (): void {
                // Arrange
                $function = new HealthFunction(requireAuthForDetails: false);

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

            test('schema requires status and timestamp fields', function (): void {
                // Arrange
                $function = new HealthFunction(requireAuthForDetails: false);

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['schema'])->toHaveKey('required')
                    ->and($array['schema']['required'])->toContain('status')
                    ->and($array['schema']['required'])->toContain('timestamp');
            });
        });

        describe('__invoke()', function (): void {
            test('returns healthy status with no checkers', function (): void {
                // Arrange
                $function = new HealthFunction([], requireAuthForDetails: false);
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:diagnostics:fn:health', [], context: ['user_id' => 'test-user']);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result)->toHaveKey('status')
                    ->and($result['status'])->toBe('healthy')
                    ->and($result)->toHaveKey('timestamp');
            });

            test('returns timestamp in ISO 8601 format', function (): void {
                // Arrange
                $function = new HealthFunction([], requireAuthForDetails: false);
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:diagnostics:fn:health', [], context: ['user_id' => 'test-user']);
                $function->setRequest($request);
                $before = CarbonImmutable::now()->subSecond();

                // Act
                $result = $function();
                $after = CarbonImmutable::now()->addSecond();

                // Assert
                expect($result)->toHaveKey('timestamp');
                $timestamp = CarbonImmutable::parse($result['timestamp']);
                expect($timestamp->between($before, $after))->toBeTrue();
            });

            test('aggregates health from multiple checkers', function (): void {
                // Arrange
                $checker1 = mock(HealthCheckerInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getName')->andReturn('database');
                    $mock->shouldReceive('check')->andReturn(
                        new HealthStatus(
                            status: 'healthy',
                            message: 'Database is responsive',
                        ),
                    );
                });

                $checker2 = mock(HealthCheckerInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getName')->andReturn('cache');
                    $mock->shouldReceive('check')->andReturn(
                        new HealthStatus(
                            status: 'healthy',
                            message: 'Cache is operational',
                        ),
                    );
                });

                $function = new HealthFunction([$checker1, $checker2], requireAuthForDetails: false);
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:diagnostics:fn:health', [], context: ['user_id' => 'test-user']);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['status'])->toBe('healthy')
                    ->and($result)->toHaveKey('components')
                    ->and($result['components'])->toHaveKey('database')
                    ->and($result['components'])->toHaveKey('cache');
            });

            test('reports degraded status when one component is degraded', function (): void {
                // Arrange
                $checker1 = mock(HealthCheckerInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getName')->andReturn('database');
                    $mock->shouldReceive('check')->andReturn(
                        new HealthStatus(status: 'healthy'),
                    );
                });

                $checker2 = mock(HealthCheckerInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getName')->andReturn('cache');
                    $mock->shouldReceive('check')->andReturn(
                        new HealthStatus(status: 'degraded'),
                    );
                });

                $function = new HealthFunction([$checker1, $checker2], requireAuthForDetails: false);
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:diagnostics:fn:health', [], context: ['user_id' => 'test-user']);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['status'])->toBe('degraded');
            });

            test('reports unhealthy status when one component is unhealthy', function (): void {
                // Arrange
                $checker1 = mock(HealthCheckerInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getName')->andReturn('database');
                    $mock->shouldReceive('check')->andReturn(
                        new HealthStatus(status: 'healthy'),
                    );
                });

                $checker2 = mock(HealthCheckerInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getName')->andReturn('cache');
                    $mock->shouldReceive('check')->andReturn(
                        new HealthStatus(status: 'unhealthy'),
                    );
                });

                $function = new HealthFunction([$checker1, $checker2], requireAuthForDetails: false);
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:diagnostics:fn:health', [], context: ['user_id' => 'test-user']);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['status'])->toBe('unhealthy');
            });

            test('filters by specific component when requested', function (): void {
                // Arrange
                $checker1 = mock(HealthCheckerInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getName')->andReturn('database');
                    $mock->shouldReceive('check')->andReturn(
                        new HealthStatus(status: 'healthy'),
                    );
                });

                $checker2 = mock(HealthCheckerInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getName')->andReturn('cache');
                });

                $function = new HealthFunction([$checker1, $checker2], requireAuthForDetails: false);
                $request = RequestObjectData::asRequest(
                    'urn:cline:forrst:ext:diagnostics:fn:health',
                    ['component' => 'database'],
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result)->toHaveKey('components')
                    ->and($result['components'])->toHaveKey('database')
                    ->and($result['components'])->not()->toHaveKey('cache');
            });

            test('returns self health when component is self', function (): void {
                // Arrange
                $function = new HealthFunction([], requireAuthForDetails: false);
                $request = RequestObjectData::asRequest(
                    'urn:cline:forrst:ext:diagnostics:fn:health',
                    ['component' => 'self'],
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['status'])->toBe('healthy')
                    ->and($result)->toHaveKey('timestamp')
                    ->and($result)->not()->toHaveKey('components');
            });

            test('includes full details when include_details is true', function (): void {
                // Arrange
                $checker = mock(HealthCheckerInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getName')->andReturn('database');
                    $mock->shouldReceive('check')->andReturn(
                        new HealthStatus(
                            status: 'healthy',
                            latency: ['value' => 5, 'unit' => 'ms'],
                            message: 'Database is responsive',
                        ),
                    );
                });

                $function = new HealthFunction([$checker], requireAuthForDetails: false);
                $request = RequestObjectData::asRequest(
                    'urn:cline:forrst:ext:diagnostics:fn:health',
                    ['include_details' => true],
                    context: ['user_id' => 'test-user'],
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['components']['database'])->toHaveKey('status')
                    ->and($result['components']['database'])->toHaveKey('latency')
                    ->and($result['components']['database'])->toHaveKey('message');
            });

            test('includes only status when include_details is false', function (): void {
                // Arrange
                $checker = mock(HealthCheckerInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getName')->andReturn('database');
                    $mock->shouldReceive('check')->andReturn(
                        new HealthStatus(
                            status: 'healthy',
                            latency: ['value' => 5, 'unit' => 'ms'],
                            message: 'Database is responsive',
                        ),
                    );
                });

                $function = new HealthFunction([$checker], requireAuthForDetails: false);
                $request = RequestObjectData::asRequest(
                    'urn:cline:forrst:ext:diagnostics:fn:health',
                    ['include_details' => false],
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['components']['database'])->toHaveKey('status')
                    ->and($result['components']['database'])->not()->toHaveKey('latency')
                    ->and($result['components']['database'])->not()->toHaveKey('message');
            });
        });
    });

    describe('Edge Cases', function (): void {
        describe('worst status determination', function (): void {
            test('unhealthy takes precedence over degraded', function (): void {
                // Arrange
                $checker1 = mock(HealthCheckerInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getName')->andReturn('component1');
                    $mock->shouldReceive('check')->andReturn(
                        new HealthStatus(status: 'degraded'),
                    );
                });

                $checker2 = mock(HealthCheckerInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getName')->andReturn('component2');
                    $mock->shouldReceive('check')->andReturn(
                        new HealthStatus(status: 'unhealthy'),
                    );
                });

                $function = new HealthFunction([$checker1, $checker2], requireAuthForDetails: false);
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:diagnostics:fn:health', [], context: ['user_id' => 'test-user']);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['status'])->toBe('unhealthy');
            });

            test('degraded takes precedence over healthy', function (): void {
                // Arrange
                $checker1 = mock(HealthCheckerInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getName')->andReturn('component1');
                    $mock->shouldReceive('check')->andReturn(
                        new HealthStatus(status: 'healthy'),
                    );
                });

                $checker2 = mock(HealthCheckerInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getName')->andReturn('component2');
                    $mock->shouldReceive('check')->andReturn(
                        new HealthStatus(status: 'degraded'),
                    );
                });

                $function = new HealthFunction([$checker1, $checker2], requireAuthForDetails: false);
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:diagnostics:fn:health', [], context: ['user_id' => 'test-user']);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['status'])->toBe('degraded');
            });

            test('multiple unhealthy components still report unhealthy', function (): void {
                // Arrange
                $checker1 = mock(HealthCheckerInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getName')->andReturn('component1');
                    $mock->shouldReceive('check')->andReturn(
                        new HealthStatus(status: 'unhealthy'),
                    );
                });

                $checker2 = mock(HealthCheckerInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getName')->andReturn('component2');
                    $mock->shouldReceive('check')->andReturn(
                        new HealthStatus(status: 'unhealthy'),
                    );
                });

                $function = new HealthFunction([$checker1, $checker2], requireAuthForDetails: false);
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:diagnostics:fn:health', [], context: ['user_id' => 'test-user']);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['status'])->toBe('unhealthy');
            });
        });

        describe('component filtering', function (): void {
            test('throws exception when filtering by non-existent component', function (): void {
                // Arrange
                $checker = mock(HealthCheckerInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getName')->andReturn('database');
                });

                $function = new HealthFunction([$checker], requireAuthForDetails: false);
                $request = RequestObjectData::asRequest(
                    'urn:cline:forrst:ext:diagnostics:fn:health',
                    ['component' => 'nonexistent'],
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())->toThrow(InvalidFieldValueException::class);
            });
        });
    });

    describe('Sad Paths', function (): void {
        test('handles checker throwing exception gracefully', function (): void {
            // Arrange
            $checker = mock(HealthCheckerInterface::class, function (MockInterface $mock): void {
                $mock->shouldReceive('getName')->andReturn('database');
                $mock->shouldReceive('check')->andThrow(
                    new RuntimeException('Database connection failed'),
                );
            });

            $function = new HealthFunction([$checker], requireAuthForDetails: false);
            $request = RequestObjectData::asRequest('urn:cline:forrst:ext:diagnostics:fn:health', [], context: ['user_id' => 'test-user']);
            $function->setRequest($request);

            // Act
            $result = $function();

            // Assert - exception should be caught and converted to unhealthy status
            expect($result['status'])->toBe('unhealthy')
                ->and($result['components']['database']['status'])->toBe('unhealthy')
                ->and($result['components']['database'])->toHaveKey('error');
        });
    });
});

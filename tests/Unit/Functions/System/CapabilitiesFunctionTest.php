<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Contracts\FunctionInterface;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Discovery\ResultDescriptorData;
use Cline\Forrst\Extensions\Discovery\Functions\CapabilitiesFunction;
use Mockery\MockInterface;

describe('CapabilitiesFunction', function (): void {
    describe('Happy Paths', function (): void {
        describe('getName()', function (): void {
            test('returns standard Forrst capabilities function name', function (): void {
                // Arrange
                $function = new CapabilitiesFunction('test-service');

                // Act
                $result = $function->getUrn();

                // Assert
                expect($result)->toBe('urn:cline:forrst:ext:discovery:fn:capabilities');
            });
        });

        describe('getVersion()', function (): void {
            test('returns default version', function (): void {
                // Arrange
                $function = new CapabilitiesFunction('test-service');

                // Act
                $result = $function->getVersion();

                // Assert
                expect($result)->toBe('1.0.0');
            });
        });

        describe('getSummary()', function (): void {
            test('returns description of capabilities function', function (): void {
                // Arrange
                $function = new CapabilitiesFunction('test-service');

                // Act
                $result = $function->getSummary();

                // Assert
                expect($result)->toBe('Discover service capabilities and supported features');
            });
        });

        describe('getArguments()', function (): void {
            test('returns empty array since capabilities requires no arguments', function (): void {
                // Arrange
                $function = new CapabilitiesFunction('test-service');

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toHaveCount(0);
            });
        });

        describe('getResult()', function (): void {
            test('returns ResultDescriptorData with capabilities response schema', function (): void {
                // Arrange
                $function = new CapabilitiesFunction('test-service');

                // Act
                $result = $function->getResult();

                // Assert
                expect($result)->toBeInstanceOf(ResultDescriptorData::class);

                $array = $result->toArray();
                expect($array)->toHaveKey('schema')
                    ->and($array['schema'])->toHaveKey('type')
                    ->and($array['schema']['type'])->toBe('object')
                    ->and($array)->toHaveKey('description')
                    ->and($array['description'])->toBe('Service capabilities response');
            });

            test('schema requires service, protocol_versions, and functions', function (): void {
                // Arrange
                $function = new CapabilitiesFunction('test-service');

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['schema'])->toHaveKey('required')
                    ->and($array['schema']['required'])->toContain('service')
                    ->and($array['schema']['required'])->toContain('protocolVersions')
                    ->and($array['schema']['required'])->toContain('functions');
            });

            test('schema defines protocol_versions as string array', function (): void {
                // Arrange
                $function = new CapabilitiesFunction('test-service');

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['schema']['properties']['protocolVersions'])->toHaveKey('type')
                    ->and($array['schema']['properties']['protocolVersions']['type'])->toBe('array')
                    ->and($array['schema']['properties']['protocolVersions']['items']['type'])->toBe('string');
            });

            test('schema defines functions as string array', function (): void {
                // Arrange
                $function = new CapabilitiesFunction('test-service');

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['schema']['properties']['functions'])->toHaveKey('type')
                    ->and($array['schema']['properties']['functions']['type'])->toBe('array')
                    ->and($array['schema']['properties']['functions']['items']['type'])->toBe('string');
            });
        });

        describe('__invoke()', function (): void {
            test('returns service name', function (): void {
                // Arrange
                $function = new CapabilitiesFunction('my-service');
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:discovery:fn:capabilities', []);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result)->toHaveKey('service')
                    ->and($result['service'])->toBe('my-service');
            });

            test('returns protocol version', function (): void {
                // Arrange
                $function = new CapabilitiesFunction('test-service');
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:discovery:fn:capabilities', []);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result)->toHaveKey('protocolVersions')
                    ->and($result['protocolVersions'])->toBeArray()
                    ->and($result['protocolVersions'])->toContain(ProtocolData::VERSION);
            });

            test('returns empty functions array when no functions registered', function (): void {
                // Arrange
                $function = new CapabilitiesFunction('test-service', []);
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:discovery:fn:capabilities', []);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result)->toHaveKey('functions')
                    ->and($result['functions'])->toBeArray()
                    ->and($result['functions'])->toHaveCount(0);
            });

            test('returns list of function names', function (): void {
                // Arrange
                $func1 = mock(FunctionInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getUrn')->andReturn('urn:app:forrst:fn:users:list');
                });

                $func2 = mock(FunctionInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getUrn')->andReturn('urn:app:forrst:fn:users:get');
                });

                $func3 = mock(FunctionInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getUrn')->andReturn('orders.create');
                });

                $function = new CapabilitiesFunction('test-service', [$func1, $func2, $func3]);
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:discovery:fn:capabilities', []);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['functions'])->toBeArray()
                    ->and($result['functions'])->toHaveCount(3)
                    ->and($result['functions'])->toContain('urn:app:forrst:fn:users:list')
                    ->and($result['functions'])->toContain('urn:app:forrst:fn:users:get')
                    ->and($result['functions'])->toContain('orders.create');
            });

            test('does not include extensions when none provided', function (): void {
                // Arrange
                $function = new CapabilitiesFunction('test-service');
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:discovery:fn:capabilities', []);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result)->not()->toHaveKey('extensions');
            });

            test('includes extensions when provided', function (): void {
                // Arrange
                $extensions = [
                    ['name' => 'streaming', 'version' => '1.0'],
                    ['name' => 'batching', 'version' => '2.1'],
                ];

                $function = new CapabilitiesFunction('test-service', [], $extensions);
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:discovery:fn:capabilities', []);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result)->toHaveKey('extensions')
                    ->and($result['extensions'])->toBeArray()
                    ->and($result['extensions'])->toHaveCount(2)
                    ->and($result['extensions'][0]['name'])->toBe('streaming')
                    ->and($result['extensions'][1]['name'])->toBe('batching');
            });

            test('does not include limits when none provided', function (): void {
                // Arrange
                $function = new CapabilitiesFunction('test-service');
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:discovery:fn:capabilities', []);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result)->not()->toHaveKey('limits');
            });

            test('includes limits when provided', function (): void {
                // Arrange
                $limits = [
                    'max_request_size' => 10_485_760, // 10MB
                    'max_batch_size' => 100,
                    'rate_limit' => ['requests' => 1_000, 'window' => 'hour'],
                ];

                $function = new CapabilitiesFunction('test-service', [], [], $limits);
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:discovery:fn:capabilities', []);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result)->toHaveKey('limits')
                    ->and($result['limits'])->toBeArray()
                    ->and($result['limits'])->toHaveKey('max_request_size')
                    ->and($result['limits']['max_request_size'])->toBe(10_485_760)
                    ->and($result['limits'])->toHaveKey('max_batch_size')
                    ->and($result['limits']['max_batch_size'])->toBe(100)
                    ->and($result['limits'])->toHaveKey('rate_limit');
            });

            test('returns complete response with all optional fields', function (): void {
                // Arrange
                $func = mock(FunctionInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getUrn')->andReturn('urn:cline:forrst:fn:test:function');
                });

                $extensions = [['name' => 'streaming', 'version' => '1.0']];
                $limits = ['max_request_size' => 5_242_880];

                $function = new CapabilitiesFunction('full-service', [$func], $extensions, $limits);
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:discovery:fn:capabilities', []);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result)->toHaveKey('service')
                    ->and($result)->toHaveKey('protocolVersions')
                    ->and($result)->toHaveKey('functions')
                    ->and($result)->toHaveKey('extensions')
                    ->and($result)->toHaveKey('limits');
            });
        });
    });

    describe('Edge Cases', function (): void {
        describe('function name extraction', function (): void {
            test('handles single function', function (): void {
                // Arrange
                $func = mock(FunctionInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('getUrn')->andReturn('single.function');
                });

                $function = new CapabilitiesFunction('test-service', [$func]);
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:discovery:fn:capabilities', []);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['functions'])->toHaveCount(1)
                    ->and($result['functions'][0])->toBe('single.function');
            });

            test('handles many functions', function (): void {
                // Arrange
                $functions = array_map(fn (int $i): FunctionInterface => mock(FunctionInterface::class, function (MockInterface $mock) use ($i): void {
                    $mock->shouldReceive('getUrn')->andReturn('function.'.$i);
                }), range(1, 50));

                $function = new CapabilitiesFunction('test-service', $functions);
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:discovery:fn:capabilities', []);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['functions'])->toHaveCount(50);
            });
        });

        describe('empty collections', function (): void {
            test('handles empty extensions array', function (): void {
                // Arrange
                $function = new CapabilitiesFunction('test-service', [], []);
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:discovery:fn:capabilities', []);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result)->not()->toHaveKey('extensions');
            });

            test('handles empty limits array', function (): void {
                // Arrange
                $function = new CapabilitiesFunction('test-service', [], [], []);
                $request = RequestObjectData::asRequest('urn:cline:forrst:ext:discovery:fn:capabilities', []);
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result)->not()->toHaveKey('limits');
            });
        });
    });

    describe('Sad Paths', function (): void {
        // Note: CapabilitiesFunction has minimal sad paths as it only
        // reports on internal state and requires no external input
        test('handles function with exception in getName gracefully', function (): void {
            // Arrange
            $func = mock(FunctionInterface::class, function (MockInterface $mock): void {
                $mock->shouldReceive('getUrn')->andThrow(
                    new RuntimeException('Failed to get name'),
                );
            });

            $function = new CapabilitiesFunction('test-service', [$func]);
            $request = RequestObjectData::asRequest('urn:cline:forrst:ext:discovery:fn:capabilities', []);
            $function->setRequest($request);

            // Act & Assert - should propagate exception
            expect(fn (): array => $function())->toThrow(RuntimeException::class);
        });
    });
});

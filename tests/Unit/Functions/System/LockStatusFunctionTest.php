<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\CallData;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Discovery\ArgumentData;
use Cline\Forrst\Discovery\ErrorDefinitionData;
use Cline\Forrst\Discovery\ResultDescriptorData;
use Cline\Forrst\Exceptions\LockKeyRequiredException;
use Cline\Forrst\Extensions\AtomicLock\AtomicLockExtension;
use Cline\Forrst\Extensions\AtomicLock\Functions\LockStatusFunction;
use Illuminate\Support\Facades\Cache;

describe('LockStatusFunction', function (): void {
    describe('Happy Paths', function (): void {
        describe('__invoke()', function (): void {
            test('returns locked status with metadata when lock exists', function (): void {
                // Arrange
                $key = 'forrst_lock:test-function:resource-123';
                $owner = 'owner-token-12345';
                $acquiredAt = '2025-12-21T10:00:00+00:00';
                $expiresAt = '2025-12-21T10:05:00+00:00';

                Cache::shouldReceive('get')->with($key.':meta:owner')->andReturn($owner);
                Cache::shouldReceive('get')->with($key.':meta:acquired_at')->andReturn($acquiredAt);
                Cache::shouldReceive('get')->with($key.':meta:expires_at')->andReturn($expiresAt);

                $extension = new AtomicLockExtension();
                $function = new LockStatusFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:status',
                        arguments: ['key' => $key],
                    ),
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['key'])->toBe($key)
                    ->and($result['locked'])->toBeTrue()
                    ->and($result['owner'])->toBe($owner)
                    ->and($result['acquired_at'])->toBe($acquiredAt)
                    ->and($result['expires_at'])->toBe($expiresAt)
                    ->and($result)->toHaveKey('ttl_remaining');
            });

            test('returns unlocked status when lock does not exist', function (): void {
                // Arrange
                $key = 'forrst_lock:non-existent';

                Cache::shouldReceive('get')->with($key.':meta:owner')->andReturn(null);

                $extension = new AtomicLockExtension();
                $function = new LockStatusFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:status',
                        arguments: ['key' => $key],
                    ),
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['key'])->toBe($key)
                    ->and($result['locked'])->toBeFalse()
                    ->and($result)->not->toHaveKey('owner');
            });
        });

        describe('getName()', function (): void {
            test('returns standard Forrst lock status function name', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockStatusFunction($extension);

                // Act
                $result = $function->getUrn();

                // Assert
                expect($result)->toBe('urn:cline:forrst:ext:atomic-lock:fn:status');
            });
        });

        describe('getSummary()', function (): void {
            test('returns description of lock status function', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockStatusFunction($extension);

                // Act
                $result = $function->getSummary();

                // Assert
                expect($result)->toBe('Check the status of a lock');
            });
        });

        describe('getArguments()', function (): void {
            test('returns key argument configuration', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockStatusFunction($extension);

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toHaveCount(1);
            });

            test('key argument is required with string type', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockStatusFunction($extension);

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result[0])->toBeInstanceOf(ArgumentData::class);
                $argumentArray = $result[0]->toArray();
                expect($argumentArray['name'])->toBe('key')
                    ->and($argumentArray['required'])->toBeTrue()
                    ->and($argumentArray['schema']['type'])->toBe('string');
            });
        });

        describe('getResult()', function (): void {
            test('returns ResultDescriptorData with status response schema', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockStatusFunction($extension);

                // Act
                $result = $function->getResult();

                // Assert
                expect($result)->toBeInstanceOf(ResultDescriptorData::class);
            });

            test('schema defines object type with key and locked properties', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockStatusFunction($extension);

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['schema']['type'])->toBe('object')
                    ->and($array['schema']['properties'])->toHaveKey('key')
                    ->and($array['schema']['properties']['key']['type'])->toBe('string')
                    ->and($array['schema']['properties'])->toHaveKey('locked')
                    ->and($array['schema']['properties']['locked']['type'])->toBe('boolean');
            });

            test('schema includes optional properties for locked status', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockStatusFunction($extension);

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['schema']['properties'])->toHaveKey('owner')
                    ->and($array['schema']['properties'])->toHaveKey('acquired_at')
                    ->and($array['schema']['properties'])->toHaveKey('expires_at')
                    ->and($array['schema']['properties'])->toHaveKey('ttl_remaining');
            });

            test('schema requires only key and locked fields', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockStatusFunction($extension);

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['schema']['required'])->toContain('key')
                    ->and($array['schema']['required'])->toContain('locked')
                    ->and($array['schema']['required'])->toHaveCount(2);
            });
        });

        describe('getErrors()', function (): void {
            test('defines InvalidArguments error', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockStatusFunction($extension);

                // Act
                $result = $function->getErrors();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toHaveCount(1)
                    ->and($result[0])->toBeInstanceOf(ErrorDefinitionData::class);
            });
        });
    });

    describe('Sad Paths', function (): void {
        describe('__invoke() with missing key', function (): void {
            test('throws InvalidArgumentException when key argument is missing', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockStatusFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:status',
                        arguments: [],
                    ),
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())
                    ->toThrow(LockKeyRequiredException::class);
            });

            test('throws InvalidArgumentException when key is empty string', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockStatusFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:status',
                        arguments: ['key' => ''],
                    ),
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())
                    ->toThrow(LockKeyRequiredException::class);
            });

            test('throws InvalidArgumentException when key is non-string type', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockStatusFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:status',
                        arguments: ['key' => 12_345],
                    ),
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())
                    ->toThrow(LockKeyRequiredException::class);
            });
        });
    });

    describe('Edge Cases', function (): void {
        describe('__invoke() with expired lock', function (): void {
            test('returns unlocked status for expired lock', function (): void {
                // Arrange
                $key = 'forrst_lock:expired-lock';

                Cache::shouldReceive('get')->with($key.':meta:owner')->andReturn(null);

                $extension = new AtomicLockExtension();
                $function = new LockStatusFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:status',
                        arguments: ['key' => $key],
                    ),
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['locked'])->toBeFalse();
            });
        });
    });
});

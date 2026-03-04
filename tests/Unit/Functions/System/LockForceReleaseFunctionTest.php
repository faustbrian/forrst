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
use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Exceptions\LockKeyRequiredException;
use Cline\Forrst\Exceptions\LockNotFoundException;
use Cline\Forrst\Extensions\AtomicLock\AtomicLockExtension;
use Cline\Forrst\Extensions\AtomicLock\Functions\LockForceReleaseFunction;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

describe('LockForceReleaseFunction', function (): void {
    describe('Happy Paths', function (): void {
        describe('__invoke()', function (): void {
            test('force releases lock successfully with valid key', function (): void {
                // Arrange
                $key = 'forrst_lock:test-function:resource-123';
                $owner = 'some-owner';

                $lock = mock(Lock::class);
                $lock->shouldReceive('forceRelease')->once()->andReturn(true);

                Cache::shouldReceive('get')->with($key.':meta:owner')->andReturn($owner);
                Cache::shouldReceive('lock')->with($key)->andReturn($lock);
                Cache::shouldReceive('forget')->times(3); // clear metadata

                $extension = new AtomicLockExtension();
                $function = new LockForceReleaseFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:force-release',
                        arguments: ['key' => $key],
                    ),
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result)->toHaveKey('released')
                    ->and($result['released'])->toBeTrue()
                    ->and($result)->toHaveKey('key')
                    ->and($result['key'])->toBe($key)
                    ->and($result)->toHaveKey('forced')
                    ->and($result['forced'])->toBeTrue();
            });

            test('returns correct structure with released true, key value, and forced true', function (): void {
                // Arrange
                $key = 'forrst_lock:my-key';
                $owner = 'my-owner';

                $lock = mock(Lock::class);
                $lock->shouldReceive('forceRelease')->andReturn(true);

                Cache::shouldReceive('get')->with($key.':meta:owner')->andReturn($owner);
                Cache::shouldReceive('lock')->with($key)->andReturn($lock);
                Cache::shouldReceive('forget')->times(3);

                $extension = new AtomicLockExtension();
                $function = new LockForceReleaseFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:force-release',
                        arguments: ['key' => $key],
                    ),
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toHaveCount(3)
                    ->and($result)->toHaveKeys(['released', 'key', 'forced']);
            });
        });

        describe('getName()', function (): void {
            test('returns standard Forrst lock force release function name', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockForceReleaseFunction($extension);

                // Act
                $result = $function->getUrn();

                // Assert
                expect($result)->toBe('urn:cline:forrst:ext:atomic-lock:fn:force-release');
            });
        });

        describe('getSummary()', function (): void {
            test('returns description of lock force release function', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockForceReleaseFunction($extension);

                // Act
                $result = $function->getSummary();

                // Assert
                expect($result)->toBe('Force release a lock without ownership check (admin only)');
            });
        });

        describe('getArguments()', function (): void {
            test('returns key argument configuration', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockForceReleaseFunction($extension);

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toHaveCount(1);
            });

            test('key argument is required with string type', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockForceReleaseFunction($extension);

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
            test('returns ResultDescriptorData with force release response schema', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockForceReleaseFunction($extension);

                // Act
                $result = $function->getResult();

                // Assert
                expect($result)->toBeInstanceOf(ResultDescriptorData::class);
            });

            test('schema defines object type with released, key, and forced properties', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockForceReleaseFunction($extension);

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['schema']['type'])->toBe('object')
                    ->and($array['schema']['properties'])->toHaveKey('released')
                    ->and($array['schema']['properties']['released']['type'])->toBe('boolean')
                    ->and($array['schema']['properties'])->toHaveKey('key')
                    ->and($array['schema']['properties']['key']['type'])->toBe('string')
                    ->and($array['schema']['properties'])->toHaveKey('forced')
                    ->and($array['schema']['properties']['forced']['type'])->toBe('boolean');
            });

            test('schema requires released, key, and forced fields', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockForceReleaseFunction($extension);

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['schema']['required'])->toContain('released')
                    ->and($array['schema']['required'])->toContain('key')
                    ->and($array['schema']['required'])->toContain('forced');
            });
        });

        describe('getErrors()', function (): void {
            test('returns array of ErrorDefinitionData with two error types', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockForceReleaseFunction($extension);

                // Act
                $result = $function->getErrors();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toHaveCount(2)
                    ->and($result[0])->toBeInstanceOf(ErrorDefinitionData::class)
                    ->and($result[1])->toBeInstanceOf(ErrorDefinitionData::class);
            });

            test('defines LockNotFound error', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockForceReleaseFunction($extension);

                // Act
                $result = $function->getErrors();

                // Assert
                $error = $result[0]->toArray();
                expect($error['code'])->toBe(ErrorCode::LockNotFound->value);
            });
        });
    });

    describe('Sad Paths', function (): void {
        describe('__invoke() with missing key', function (): void {
            test('throws InvalidArgumentException when key argument is missing', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockForceReleaseFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:force-release',
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
                $function = new LockForceReleaseFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:force-release',
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
                $function = new LockForceReleaseFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:force-release',
                        arguments: ['key' => 12_345],
                    ),
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())
                    ->toThrow(LockKeyRequiredException::class);
            });
        });

        describe('__invoke() with non-existent lock', function (): void {
            test('throws LockNotFoundException when lock does not exist', function (): void {
                // Arrange
                $key = 'non-existent-lock';

                Cache::shouldReceive('get')->with($key.':meta:owner')->andReturn(null);

                $extension = new AtomicLockExtension();
                $function = new LockForceReleaseFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:force-release',
                        arguments: ['key' => $key],
                    ),
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())
                    ->toThrow(LockNotFoundException::class);
            });
        });
    });
});

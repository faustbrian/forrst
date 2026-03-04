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
use Cline\Forrst\Exceptions\LockOwnerRequiredException;
use Cline\Forrst\Exceptions\LockOwnershipMismatchException;
use Cline\Forrst\Extensions\AtomicLock\AtomicLockExtension;
use Cline\Forrst\Extensions\AtomicLock\Functions\LockReleaseFunction;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

describe('LockReleaseFunction', function (): void {
    describe('Happy Paths', function (): void {
        describe('__invoke()', function (): void {
            test('releases lock successfully with valid key and owner', function (): void {
                // Arrange
                $key = 'forrst_lock:test-function:resource-123';
                $owner = 'owner-token-12345';

                $lock = mock(Lock::class);
                $lock->shouldReceive('release')->once()->andReturn(true);

                Cache::shouldReceive('get')->with($key.':meta:owner')->andReturn($owner);
                Cache::shouldReceive('restoreLock')->with($key, $owner)->andReturn($lock);
                Cache::shouldReceive('forget')->times(3); // clear metadata

                $extension = new AtomicLockExtension();
                $function = new LockReleaseFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:release',
                        arguments: ['key' => $key, 'owner' => $owner],
                    ),
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result)->toHaveKey('released')
                    ->and($result['released'])->toBeTrue()
                    ->and($result)->toHaveKey('key')
                    ->and($result['key'])->toBe($key);
            });

            test('returns correct structure with released true and key value', function (): void {
                // Arrange
                $key = 'forrst_lock:my-key';
                $owner = 'my-owner';

                $lock = mock(Lock::class);
                $lock->shouldReceive('release')->andReturn(true);

                Cache::shouldReceive('get')->with($key.':meta:owner')->andReturn($owner);
                Cache::shouldReceive('restoreLock')->with($key, $owner)->andReturn($lock);
                Cache::shouldReceive('forget')->times(3);

                $extension = new AtomicLockExtension();
                $function = new LockReleaseFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:release',
                        arguments: ['key' => $key, 'owner' => $owner],
                    ),
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toHaveCount(2)
                    ->and($result)->toHaveKeys(['released', 'key']);
            });
        });

        describe('getName()', function (): void {
            test('returns standard Forrst lock release function name', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockReleaseFunction($extension);

                // Act
                $result = $function->getUrn();

                // Assert
                expect($result)->toBe('urn:cline:forrst:ext:atomic-lock:fn:release');
            });
        });

        describe('getSummary()', function (): void {
            test('returns description of lock release function', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockReleaseFunction($extension);

                // Act
                $result = $function->getSummary();

                // Assert
                expect($result)->toBe('Release a lock with ownership verification');
            });
        });

        describe('getArguments()', function (): void {
            test('returns key and owner argument configurations', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockReleaseFunction($extension);

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toHaveCount(2);
            });

            test('key argument is required with string type', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockReleaseFunction($extension);

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result[0])->toBeInstanceOf(ArgumentData::class);
                $argumentArray = $result[0]->toArray();
                expect($argumentArray['name'])->toBe('key')
                    ->and($argumentArray['required'])->toBeTrue()
                    ->and($argumentArray['schema']['type'])->toBe('string');
            });

            test('owner argument is required with string type', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockReleaseFunction($extension);

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result[1])->toBeInstanceOf(ArgumentData::class);
                $argumentArray = $result[1]->toArray();
                expect($argumentArray['name'])->toBe('owner')
                    ->and($argumentArray['required'])->toBeTrue()
                    ->and($argumentArray['schema']['type'])->toBe('string');
            });
        });

        describe('getResult()', function (): void {
            test('returns ResultDescriptorData with release response schema', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockReleaseFunction($extension);

                // Act
                $result = $function->getResult();

                // Assert
                expect($result)->toBeInstanceOf(ResultDescriptorData::class);
            });

            test('schema defines object type with released and key properties', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockReleaseFunction($extension);

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['schema']['type'])->toBe('object')
                    ->and($array['schema']['properties'])->toHaveKey('released')
                    ->and($array['schema']['properties']['released']['type'])->toBe('boolean')
                    ->and($array['schema']['properties'])->toHaveKey('key')
                    ->and($array['schema']['properties']['key']['type'])->toBe('string');
            });

            test('schema requires released and key fields', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockReleaseFunction($extension);

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['schema']['required'])->toContain('released')
                    ->and($array['schema']['required'])->toContain('key');
            });
        });

        describe('getErrors()', function (): void {
            test('returns array of ErrorDefinitionData with two error types', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockReleaseFunction($extension);

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
                $function = new LockReleaseFunction($extension);

                // Act
                $result = $function->getErrors();

                // Assert
                $error = $result[0]->toArray();
                expect($error['code'])->toBe(ErrorCode::LockNotFound->value);
            });

            test('defines LockOwnershipMismatch error', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockReleaseFunction($extension);

                // Act
                $result = $function->getErrors();

                // Assert
                $error = $result[1]->toArray();
                expect($error['code'])->toBe(ErrorCode::LockOwnershipMismatch->value);
            });
        });
    });

    describe('Sad Paths', function (): void {
        describe('__invoke() with missing key', function (): void {
            test('throws LockKeyRequiredException when key argument is missing', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockReleaseFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:release',
                        arguments: ['owner' => 'some-owner'],
                    ),
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())
                    ->toThrow(LockKeyRequiredException::class);
            });

            test('throws LockKeyRequiredException when key is empty string', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockReleaseFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:release',
                        arguments: ['key' => '', 'owner' => 'some-owner'],
                    ),
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())
                    ->toThrow(LockKeyRequiredException::class);
            });
        });

        describe('__invoke() with missing owner', function (): void {
            test('throws LockOwnerRequiredException when owner argument is missing', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockReleaseFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:release',
                        arguments: ['key' => 'some-key'],
                    ),
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())
                    ->toThrow(LockOwnerRequiredException::class);
            });

            test('throws LockOwnerRequiredException when owner is empty string', function (): void {
                // Arrange
                $extension = new AtomicLockExtension();
                $function = new LockReleaseFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:release',
                        arguments: ['key' => 'some-key', 'owner' => ''],
                    ),
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())
                    ->toThrow(LockOwnerRequiredException::class);
            });
        });

        describe('__invoke() with non-existent lock', function (): void {
            test('throws LockNotFoundException when lock does not exist', function (): void {
                // Arrange
                $key = 'non-existent-lock';
                $owner = 'some-owner';

                Cache::shouldReceive('get')->with($key.':meta:owner')->andReturn(null);

                $extension = new AtomicLockExtension();
                $function = new LockReleaseFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:release',
                        arguments: ['key' => $key, 'owner' => $owner],
                    ),
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())
                    ->toThrow(LockNotFoundException::class);
            });
        });

        describe('__invoke() with wrong owner', function (): void {
            test('throws LockOwnershipMismatchException when owner does not match', function (): void {
                // Arrange
                $key = 'existing-lock';
                $owner = 'wrong-owner';
                $actualOwner = 'correct-owner';

                Cache::shouldReceive('get')->with($key.':meta:owner')->andReturn($actualOwner);

                $extension = new AtomicLockExtension();
                $function = new LockReleaseFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:atomic-lock:fn:release',
                        arguments: ['key' => $key, 'owner' => $owner],
                    ),
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())
                    ->toThrow(LockOwnershipMismatchException::class);
            });
        });
    });
});

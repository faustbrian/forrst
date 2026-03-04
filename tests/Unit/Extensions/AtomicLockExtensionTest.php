<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\CallData;
use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Events\ExecutingFunction;
use Cline\Forrst\Events\FunctionExecuted;
use Cline\Forrst\Exceptions\LockAcquisitionFailedException;
use Cline\Forrst\Exceptions\LockKeyRequiredException;
use Cline\Forrst\Exceptions\LockNotFoundException;
use Cline\Forrst\Exceptions\LockOwnershipMismatchException;
use Cline\Forrst\Exceptions\LockTimeoutException;
use Cline\Forrst\Exceptions\LockTtlRequiredException;
use Cline\Forrst\Extensions\AtomicLock\AtomicLockExtension;
use Cline\Forrst\Extensions\ExtensionUrn;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException as LaravelLockTimeoutException;
use Illuminate\Support\Facades\Cache;

describe('AtomicLockExtension', function (): void {
    describe('Happy Paths', function (): void {
        test('getUrn returns correct URN constant', function (): void {
            // Arrange
            $extension = new AtomicLockExtension();

            // Act
            $urn = $extension->getUrn();

            // Assert
            expect($urn)->toBe(ExtensionUrn::AtomicLock->value)
                ->and($urn)->toBe('urn:cline:forrst:ext:atomic-lock');
        });

        test('buildLockKey creates function-scoped key by default', function (): void {
            // Arrange
            $extension = new AtomicLockExtension();

            // Act
            $key = $extension->buildLockKey('user:123', AtomicLockExtension::SCOPE_FUNCTION, 'payments.charge');

            // Assert
            expect($key)->toBe('forrst_lock:payments.charge:user:123');
        });

        test('buildLockKey creates global key when scope is global', function (): void {
            // Arrange
            $extension = new AtomicLockExtension();

            // Act
            $key = $extension->buildLockKey('billing:report', AtomicLockExtension::SCOPE_GLOBAL, 'payments.charge');

            // Assert
            expect($key)->toBe('forrst_lock:billing:report');
        });

        test('onExecutingFunction acquires lock successfully with auto-release', function (): void {
            // Arrange
            $lock = mock(Lock::class);
            $lock->shouldReceive('get')->andReturn(true);

            Cache::shouldReceive('lock')->andReturn($lock);
            Cache::shouldReceive('put')->times(3); // metadata: owner, acquired_at, expires_at

            $extension = new AtomicLockExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0001',
                call: new CallData(function: 'urn:cline:forrst:fn:payments:charge', arguments: ['amount' => 100]),
            );
            $extensionData = ExtensionData::request(ExtensionUrn::AtomicLock->value, [
                'key' => 'user:123',
                'ttl' => ['value' => 30, 'unit' => 'second'],
            ]);
            $event = new ExecutingFunction($request, $extensionData);

            // Act
            $extension->onExecutingFunction($event);

            // Assert
            expect($event->getResponse())->toBeNull()
                ->and($event->isPropagationStopped())->toBeFalse();
        });

        test('onExecutingFunction acquires lock with blocking', function (): void {
            // Arrange
            $lock = mock(Lock::class);
            $lock->shouldReceive('block')->with(5)->andReturn(true);

            Cache::shouldReceive('lock')->andReturn($lock);
            Cache::shouldReceive('put')->times(3);

            $extension = new AtomicLockExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0002',
                call: new CallData(function: 'test', arguments: []),
            );
            $extensionData = ExtensionData::request(ExtensionUrn::AtomicLock->value, [
                'key' => 'resource',
                'ttl' => ['value' => 60, 'unit' => 'second'],
                'block' => ['value' => 5, 'unit' => 'second'],
            ]);
            $event = new ExecutingFunction($request, $extensionData);

            // Act
            $extension->onExecutingFunction($event);

            // Assert
            expect($event->getResponse())->toBeNull()
                ->and($event->isPropagationStopped())->toBeFalse();
        });

        test('onFunctionExecuted releases lock when auto_release is true', function (): void {
            // Arrange
            $lock = mock(Lock::class);
            $lock->shouldReceive('get')->andReturn(true);
            $lock->shouldReceive('release')->once()->andReturn(true);

            Cache::shouldReceive('lock')->andReturn($lock);
            Cache::shouldReceive('put')->times(3);
            Cache::shouldReceive('forget')->times(3); // cleanup metadata

            $extension = new AtomicLockExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0003',
                call: new CallData(function: 'test', arguments: []),
            );
            $extensionData = ExtensionData::request(ExtensionUrn::AtomicLock->value, [
                'key' => 'auto-release',
                'ttl' => ['value' => 30, 'unit' => 'second'],
            ]);
            $response = ResponseData::success(['ok' => true], '01JFEX0003');

            // Set up context first
            $executingEvent = new ExecutingFunction($request, $extensionData);
            $extension->onExecutingFunction($executingEvent);

            // Act
            $executedEvent = new FunctionExecuted($request, $extensionData, $response);
            $extension->onFunctionExecuted($executedEvent);

            // Assert
            $result = $executedEvent->getResponse();
            expect($result)->toBeInstanceOf(ResponseData::class)
                ->and($result->extensions)->toHaveCount(1);

            $ext = $result->extensions[0];
            expect($ext->urn)->toBe(ExtensionUrn::AtomicLock->value)
                ->and($ext->data)->toHaveKey('key', 'auto-release')
                ->and($ext->data)->toHaveKey('acquired', true)
                ->and($ext->data)->toHaveKey('owner')
                ->and($ext->data)->toHaveKey('scope', 'function')
                ->and($ext->data)->toHaveKey('expires_at');
        });

        test('onFunctionExecuted does not release lock when auto_release is false', function (): void {
            // Arrange
            $lock = mock(Lock::class);
            $lock->shouldReceive('get')->andReturn(true);
            $lock->shouldNotReceive('release');

            Cache::shouldReceive('lock')->andReturn($lock);
            Cache::shouldReceive('put')->times(3);
            // Should NOT forget metadata when auto_release is false

            $extension = new AtomicLockExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0004',
                call: new CallData(function: 'test', arguments: []),
            );
            $extensionData = ExtensionData::request(ExtensionUrn::AtomicLock->value, [
                'key' => 'manual-release',
                'ttl' => ['value' => 1, 'unit' => 'hour'],
                'auto_release' => false,
            ]);
            $response = ResponseData::success(['ok' => true], '01JFEX0004');

            // Set up context first
            $executingEvent = new ExecutingFunction($request, $extensionData);
            $extension->onExecutingFunction($executingEvent);

            // Act
            $executedEvent = new FunctionExecuted($request, $extensionData, $response);
            $extension->onFunctionExecuted($executedEvent);

            // Assert
            $result = $executedEvent->getResponse();
            expect($result->extensions[0]->data)->toHaveKey('owner');
        });

        test('releaseLock releases lock with correct owner', function (): void {
            // Arrange
            $lock = mock(Lock::class);
            $lock->shouldReceive('release')->once()->andReturn(true);

            Cache::shouldReceive('get')
                ->with('forrst_lock:test:meta:owner')
                ->andReturn('owner-token-123');
            Cache::shouldReceive('restoreLock')
                ->with('forrst_lock:test', 'owner-token-123')
                ->andReturn($lock);
            Cache::shouldReceive('forget')->times(3);

            $extension = new AtomicLockExtension();

            // Act
            $result = $extension->releaseLock('forrst_lock:test', 'owner-token-123');

            // Assert
            expect($result)->toBeTrue();
        });

        test('forceReleaseLock releases lock without ownership check', function (): void {
            // Arrange
            $lock = mock(Lock::class);
            $lock->shouldReceive('forceRelease')->once();

            Cache::shouldReceive('get')
                ->with('forrst_lock:test:meta:owner')
                ->andReturn('some-owner');
            Cache::shouldReceive('lock')
                ->with('forrst_lock:test')
                ->andReturn($lock);
            Cache::shouldReceive('forget')->times(3);

            $extension = new AtomicLockExtension();

            // Act
            $result = $extension->forceReleaseLock('forrst_lock:test');

            // Assert
            expect($result)->toBeTrue();
        });

        test('getLockStatus returns locked status with metadata', function (): void {
            // Arrange
            Cache::shouldReceive('get')
                ->with('forrst_lock:test:meta:owner')
                ->andReturn('owner-123');
            Cache::shouldReceive('get')
                ->with('forrst_lock:test:meta:acquired_at')
                ->andReturn('2024-01-01T10:00:00Z');
            Cache::shouldReceive('get')
                ->with('forrst_lock:test:meta:expires_at')
                ->andReturn(now()->addMinutes(5)->toIso8601String());

            $extension = new AtomicLockExtension();

            // Act
            $status = $extension->getLockStatus('forrst_lock:test');

            // Assert
            expect($status)->toHaveKey('key', 'forrst_lock:test')
                ->and($status)->toHaveKey('locked', true)
                ->and($status)->toHaveKey('owner', 'owner-123')
                ->and($status)->toHaveKey('acquired_at')
                ->and($status)->toHaveKey('expires_at')
                ->and($status)->toHaveKey('ttl_remaining');
        });

        test('getLockStatus returns unlocked status when no lock exists', function (): void {
            // Arrange
            Cache::shouldReceive('get')
                ->with('forrst_lock:test:meta:owner')
                ->andReturn(null);

            $extension = new AtomicLockExtension();

            // Act
            $status = $extension->getLockStatus('forrst_lock:test');

            // Assert
            expect($status)->toBe([
                'key' => 'forrst_lock:test',
                'locked' => false,
            ]);
        });
    });

    describe('Edge Cases', function (): void {
        test('onExecutingFunction uses custom owner token when provided', function (): void {
            // Arrange
            $lock = mock(Lock::class);
            $lock->shouldReceive('get')->andReturn(true);

            Cache::shouldReceive('lock')
                ->with(Mockery::any(), 30, 'my-custom-owner')
                ->andReturn($lock);
            Cache::shouldReceive('put')->times(3);

            $extension = new AtomicLockExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0005',
                call: new CallData(function: 'test', arguments: []),
            );
            $extensionData = ExtensionData::request(ExtensionUrn::AtomicLock->value, [
                'key' => 'custom',
                'ttl' => ['value' => 30, 'unit' => 'second'],
                'owner' => 'my-custom-owner',
            ]);
            $event = new ExecutingFunction($request, $extensionData);

            // Act
            $extension->onExecutingFunction($event);

            // Assert
            expect($event->getResponse())->toBeNull();
        });

        test('onExecutingFunction uses global scope when specified', function (): void {
            // Arrange
            $lock = mock(Lock::class);
            $lock->shouldReceive('get')->andReturn(true);

            Cache::shouldReceive('lock')
                ->with('forrst_lock:billing:global-key', Mockery::any(), Mockery::any())
                ->andReturn($lock);
            Cache::shouldReceive('put')->times(3);

            $extension = new AtomicLockExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0006',
                call: new CallData(function: 'urn:cline:forrst:fn:billing:generate', arguments: []),
            );
            $extensionData = ExtensionData::request(ExtensionUrn::AtomicLock->value, [
                'key' => 'billing:global-key',
                'ttl' => ['value' => 1, 'unit' => 'hour'],
                'scope' => 'global',
            ]);
            $event = new ExecutingFunction($request, $extensionData);

            // Act
            $extension->onExecutingFunction($event);

            // Assert
            expect($event->getResponse())->toBeNull();
        });

        test('onFunctionExecuted does nothing when context is null', function (): void {
            // Arrange
            $extension = new AtomicLockExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0007',
                call: new CallData(function: 'test', arguments: []),
            );
            $response = ResponseData::success(['ok' => true], '01JFEX0007');
            $extensionData = ExtensionData::request(ExtensionUrn::AtomicLock->value, []);

            // Act - skip onExecutingFunction so context is null
            $event = new FunctionExecuted($request, $extensionData, $response);
            $extension->onFunctionExecuted($event);

            // Assert
            expect($event->getResponse())->toBe($response);
        });

        test('parseDuration handles minutes correctly', function (): void {
            // Arrange
            $lock = mock(Lock::class);
            $lock->shouldReceive('get')->andReturn(true);

            Cache::shouldReceive('lock')
                ->with(Mockery::any(), 300, Mockery::any()) // 5 minutes = 300 seconds
                ->andReturn($lock);
            Cache::shouldReceive('put')->times(3);

            $extension = new AtomicLockExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0008',
                call: new CallData(function: 'test', arguments: []),
            );
            $extensionData = ExtensionData::request(ExtensionUrn::AtomicLock->value, [
                'key' => 'duration',
                'ttl' => ['value' => 5, 'unit' => 'minute'],
            ]);
            $event = new ExecutingFunction($request, $extensionData);

            // Act
            $extension->onExecutingFunction($event);

            // Assert
            expect($event->getResponse())->toBeNull();
        });

        test('parseDuration handles hours correctly', function (): void {
            // Arrange
            $lock = mock(Lock::class);
            $lock->shouldReceive('get')->andReturn(true);

            Cache::shouldReceive('lock')
                ->with(Mockery::any(), 7_200, Mockery::any()) // 2 hours = 7200 seconds
                ->andReturn($lock);
            Cache::shouldReceive('put')->times(3);

            $extension = new AtomicLockExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0009',
                call: new CallData(function: 'test', arguments: []),
            );
            $extensionData = ExtensionData::request(ExtensionUrn::AtomicLock->value, [
                'key' => 'hours',
                'ttl' => ['value' => 2, 'unit' => 'hour'],
            ]);
            $event = new ExecutingFunction($request, $extensionData);

            // Act
            $extension->onExecutingFunction($event);

            // Assert
            expect($event->getResponse())->toBeNull();
        });

        test('parseDuration handles days correctly', function (): void {
            // Arrange
            $lock = mock(Lock::class);
            $lock->shouldReceive('get')->andReturn(true);

            Cache::shouldReceive('lock')
                ->with(Mockery::any(), 86_400, Mockery::any()) // 1 day = 86400 seconds
                ->andReturn($lock);
            Cache::shouldReceive('put')->times(3);

            $extension = new AtomicLockExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0010',
                call: new CallData(function: 'test', arguments: []),
            );
            $extensionData = ExtensionData::request(ExtensionUrn::AtomicLock->value, [
                'key' => 'days',
                'ttl' => ['value' => 1, 'unit' => 'day'],
            ]);
            $event = new ExecutingFunction($request, $extensionData);

            // Act
            $extension->onExecutingFunction($event);

            // Assert
            expect($event->getResponse())->toBeNull();
        });

        test('getLockStatus returns 0 for ttl_remaining when lock is expired', function (): void {
            // Arrange
            Cache::shouldReceive('get')
                ->with('forrst_lock:test:meta:owner')
                ->andReturn('owner-123');
            Cache::shouldReceive('get')
                ->with('forrst_lock:test:meta:acquired_at')
                ->andReturn('2024-01-01T10:00:00Z');
            Cache::shouldReceive('get')
                ->with('forrst_lock:test:meta:expires_at')
                ->andReturn(now()->subMinutes(5)->toIso8601String()); // Expired

            $extension = new AtomicLockExtension();

            // Act
            $status = $extension->getLockStatus('forrst_lock:test');

            // Assert
            expect($status['ttl_remaining'])->toBe(0);
        });
    });

    describe('Sad Paths', function (): void {
        test('onExecutingFunction throws when key is missing', function (): void {
            // Arrange
            $extension = new AtomicLockExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0011',
                call: new CallData(function: 'test', arguments: []),
            );
            $extensionData = ExtensionData::request(ExtensionUrn::AtomicLock->value, [
                'ttl' => ['value' => 30, 'unit' => 'second'],
            ]);
            $event = new ExecutingFunction($request, $extensionData);

            // Act & Assert
            expect(fn () => $extension->onExecutingFunction($event))
                ->toThrow(LockKeyRequiredException::class);
        });

        test('onExecutingFunction throws when ttl is missing', function (): void {
            // Arrange
            $extension = new AtomicLockExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0012',
                call: new CallData(function: 'test', arguments: []),
            );
            $extensionData = ExtensionData::request(ExtensionUrn::AtomicLock->value, [
                'key' => 'missing-ttl',
            ]);
            $event = new ExecutingFunction($request, $extensionData);

            // Act & Assert
            expect(fn () => $extension->onExecutingFunction($event))
                ->toThrow(LockTtlRequiredException::class);
        });

        test('onExecutingFunction throws LockAcquisitionFailedException when lock is held', function (): void {
            // Arrange
            $lock = mock(Lock::class);
            $lock->shouldReceive('get')->andReturn(false);

            Cache::shouldReceive('lock')->andReturn($lock);

            $extension = new AtomicLockExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0013',
                call: new CallData(function: 'test', arguments: []),
            );
            $extensionData = ExtensionData::request(ExtensionUrn::AtomicLock->value, [
                'key' => 'locked-resource',
                'ttl' => ['value' => 30, 'unit' => 'second'],
            ]);
            $event = new ExecutingFunction($request, $extensionData);

            // Act & Assert
            expect(fn () => $extension->onExecutingFunction($event))
                ->toThrow(LockAcquisitionFailedException::class);
        });

        test('onExecutingFunction throws LockTimeoutException when blocking times out', function (): void {
            // Arrange
            $lock = mock(Lock::class);
            $lock->shouldReceive('block')->andThrow(
                new LaravelLockTimeoutException(),
            );

            Cache::shouldReceive('lock')->andReturn($lock);

            $extension = new AtomicLockExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0014',
                call: new CallData(function: 'test', arguments: []),
            );
            $extensionData = ExtensionData::request(ExtensionUrn::AtomicLock->value, [
                'key' => 'timeout-resource',
                'ttl' => ['value' => 60, 'unit' => 'second'],
                'block' => ['value' => 5, 'unit' => 'second'],
            ]);
            $event = new ExecutingFunction($request, $extensionData);

            // Act & Assert
            expect(fn () => $extension->onExecutingFunction($event))
                ->toThrow(LockTimeoutException::class);
        });

        test('releaseLock throws LockNotFoundException when lock does not exist', function (): void {
            // Arrange
            Cache::shouldReceive('get')
                ->with('forrst_lock:missing:meta:owner')
                ->andReturn(null);

            $extension = new AtomicLockExtension();

            // Act & Assert
            expect(fn (): bool => $extension->releaseLock('forrst_lock:missing', 'any-owner'))
                ->toThrow(LockNotFoundException::class);
        });

        test('releaseLock throws LockOwnershipMismatchException when owner does not match', function (): void {
            // Arrange
            Cache::shouldReceive('get')
                ->with('forrst_lock:test:meta:owner')
                ->andReturn('correct-owner');

            $extension = new AtomicLockExtension();

            // Act & Assert
            expect(fn (): bool => $extension->releaseLock('forrst_lock:test', 'wrong-owner'))
                ->toThrow(LockOwnershipMismatchException::class);
        });

        test('forceReleaseLock throws LockNotFoundException when lock does not exist', function (): void {
            // Arrange
            Cache::shouldReceive('get')
                ->with('forrst_lock:missing:meta:owner')
                ->andReturn(null);

            $extension = new AtomicLockExtension();

            // Act & Assert
            expect(fn (): bool => $extension->forceReleaseLock('forrst_lock:missing'))
                ->toThrow(LockNotFoundException::class);
        });
    });
});

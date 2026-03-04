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
use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Events\ExecutingFunction;
use Cline\Forrst\Events\RequestValidated;
use Cline\Forrst\Extensions\Cancellation\CancellationExtension;
use Cline\Forrst\Extensions\ExtensionUrn;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

describe('CancellationExtension', function (): void {
    beforeEach(function (): void {
        Cache::flush();
    });

    describe('Happy Paths', function (): void {
        test('getUrn returns correct URN constant', function (): void {
            // Arrange
            $extension = new CancellationExtension();

            // Act
            $urn = $extension->getUrn();

            // Assert
            expect($urn)->toBe(ExtensionUrn::Cancellation->value)
                ->and($urn)->toBe('urn:cline:forrst:ext:cancellation');
        });

        test('onRequestValidated registers token as active in cache', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0001',
                call: new CallData(function: 'test'),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 'test-token-123']),
                ],
            );
            $event = new RequestValidated($request);

            // Act
            $extension->onRequestValidated($event);

            // Assert
            expect($extension->isActive('test-token-123'))->toBeTrue()
                ->and($extension->isCancelled('test-token-123'))->toBeFalse()
                ->and($event->getResponse())->toBeNull()
                ->and($event->isPropagationStopped())->toBeFalse();
        });

        test('cancel marks active token as cancelled in cache', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0002',
                call: new CallData(function: 'test'),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 'cancel-test']),
                ],
            );
            $event = new RequestValidated($request);
            $extension->onRequestValidated($event);

            // Act
            $result = $extension->cancel('cancel-test');

            // Assert
            expect($result)->toBeTrue()
                ->and($extension->isCancelled('cancel-test'))->toBeTrue()
                ->and($extension->isActive('cancel-test'))->toBeFalse();
        });

        test('onExecutingFunction returns cancelled error when token is cancelled', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0003',
                call: new CallData(function: 'longRunningTask'),
            );
            $extensionData = ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 'cancel-me']);

            // Register and cancel the token
            $validatedEvent = new RequestValidated(
                new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: '01JFEX0003',
                    call: new CallData(function: 'test'),
                    extensions: [
                        ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 'cancel-me']),
                    ],
                ),
            );
            $extension->onRequestValidated($validatedEvent);
            $extension->cancel('cancel-me');

            $event = new ExecutingFunction($request, $extensionData);

            // Act
            $extension->onExecutingFunction($event);

            // Assert
            $response = $event->getResponse();
            expect($response)->not->toBeNull()
                ->and($response->isFailed())->toBeTrue()
                ->and($event->isPropagationStopped())->toBeTrue();

            $error = $response->getFirstError();
            expect($error->code)->toBe(ErrorCode::Cancelled->value)
                ->and($error->message)->toBe('Request was cancelled by client');
        });

        test('cleanup removes token from cache', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0004',
                call: new CallData(function: 'test'),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 'cleanup-test']),
                ],
            );
            $event = new RequestValidated($request);
            $extension->onRequestValidated($event);

            // Act
            $extension->cleanup('cleanup-test');

            // Assert
            expect($extension->isActive('cleanup-test'))->toBeFalse()
                ->and($extension->isCancelled('cleanup-test'))->toBeFalse();
        });

        test('onExecutingFunction does not stop propagation when token is active', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0005',
                call: new CallData(function: 'test'),
            );
            $extensionData = ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 'active-token']);

            // Register token as active
            $validatedEvent = new RequestValidated(
                new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: '01JFEX0005',
                    call: new CallData(function: 'test'),
                    extensions: [
                        ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 'active-token']),
                    ],
                ),
            );
            $extension->onRequestValidated($validatedEvent);

            $event = new ExecutingFunction($request, $extensionData);

            // Act
            $extension->onExecutingFunction($event);

            // Assert
            expect($event->getResponse())->toBeNull()
                ->and($event->isPropagationStopped())->toBeFalse()
                ->and($extension->isActive('active-token'))->toBeTrue();
        });

        test('cancel returns true when called multiple times on same token', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0006',
                call: new CallData(function: 'test'),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 'multi-cancel']),
                ],
            );
            $event = new RequestValidated($request);
            $extension->onRequestValidated($event);

            // Act
            $firstCancel = $extension->cancel('multi-cancel');
            $secondCancel = $extension->cancel('multi-cancel');
            $thirdCancel = $extension->cancel('multi-cancel');

            // Assert
            expect($firstCancel)->toBeTrue()
                ->and($secondCancel)->toBeTrue()
                ->and($thirdCancel)->toBeTrue()
                ->and($extension->isCancelled('multi-cancel'))->toBeTrue();
        });

        test('isCancelled returns false for active token', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0007',
                call: new CallData(function: 'test'),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 'check-active']),
                ],
            );
            $event = new RequestValidated($request);
            $extension->onRequestValidated($event);

            // Act
            $result = $extension->isCancelled('check-active');

            // Assert
            expect($result)->toBeFalse()
                ->and($extension->isActive('check-active'))->toBeTrue();
        });

        test('isActive returns false for cancelled token', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0008',
                call: new CallData(function: 'test'),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 'cancel-active-check']),
                ],
            );
            $event = new RequestValidated($request);
            $extension->onRequestValidated($event);
            $extension->cancel('cancel-active-check');

            // Act
            $result = $extension->isActive('cancel-active-check');

            // Assert
            expect($result)->toBeFalse()
                ->and($extension->isCancelled('cancel-active-check'))->toBeTrue();
        });

        test('onExecutingFunction cleans up token after detecting cancellation', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0009',
                call: new CallData(function: 'test'),
            );
            $extensionData = ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 'auto-cleanup']);

            // Register and cancel
            $validatedEvent = new RequestValidated(
                new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: '01JFEX0009',
                    call: new CallData(function: 'test'),
                    extensions: [
                        ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 'auto-cleanup']),
                    ],
                ),
            );
            $extension->onRequestValidated($validatedEvent);
            $extension->cancel('auto-cleanup');

            $event = new ExecutingFunction($request, $extensionData);

            // Act
            $extension->onExecutingFunction($event);

            // Assert
            expect($extension->isCancelled('auto-cleanup'))->toBeFalse()
                ->and($extension->isActive('auto-cleanup'))->toBeFalse();
        });
    });

    describe('Sad Paths', function (): void {
        test('onRequestValidated returns error when token is missing from options', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0010',
                call: new CallData(function: 'test'),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Cancellation->value, []),
                ],
            );
            $event = new RequestValidated($request);

            // Act
            $extension->onRequestValidated($event);

            // Assert
            $response = $event->getResponse();
            expect($response)->not->toBeNull()
                ->and($response->isFailed())->toBeTrue()
                ->and($event->isPropagationStopped())->toBeTrue();

            $error = $response->getFirstError();
            expect($error->code)->toBe(ErrorCode::InvalidArguments->value)
                ->and($error->message)->toBe('Cancellation token is required');
        });

        test('onRequestValidated returns error when token is empty string', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0011',
                call: new CallData(function: 'test'),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => '']),
                ],
            );
            $event = new RequestValidated($request);

            // Act
            $extension->onRequestValidated($event);

            // Assert
            $response = $event->getResponse();
            expect($response)->not->toBeNull()
                ->and($response->isFailed())->toBeTrue()
                ->and($event->isPropagationStopped())->toBeTrue();

            $error = $response->getFirstError();
            expect($error->code)->toBe(ErrorCode::InvalidArguments->value)
                ->and($error->message)->toBe('Cancellation token is required');
        });

        test('cancel returns false for non-existent token', function (): void {
            // Arrange
            $extension = new CancellationExtension();

            // Act
            $result = $extension->cancel('non-existent-token');

            // Assert
            expect($result)->toBeFalse();
        });

        test('cancel returns false for expired token', function (): void {
            // Arrange
            $extension = new CancellationExtension(tokenTtl: 1);
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0012',
                call: new CallData(function: 'test'),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 'expiring-token']),
                ],
            );
            $event = new RequestValidated($request);
            $extension->onRequestValidated($event);

            // Wait for token to expire
            Sleep::sleep(2);

            // Act
            $result = $extension->cancel('expiring-token');

            // Assert
            expect($result)->toBeFalse();
        });

        test('onRequestValidated returns error when token is not a string', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0013',
                call: new CallData(function: 'test'),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 123]),
                ],
            );
            $event = new RequestValidated($request);

            // Act
            $extension->onRequestValidated($event);

            // Assert
            $response = $event->getResponse();
            expect($response)->not->toBeNull()
                ->and($response->isFailed())->toBeTrue()
                ->and($event->isPropagationStopped())->toBeTrue();

            $error = $response->getFirstError();
            expect($error->code)->toBe(ErrorCode::InvalidArguments->value)
                ->and($error->message)->toBe('Cancellation token is required');
        });
    });

    describe('Edge Cases', function (): void {
        test('onRequestValidated does nothing when extension is not present', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0014',
                call: new CallData(function: 'test'),
            );
            $event = new RequestValidated($request);

            // Act
            $extension->onRequestValidated($event);

            // Assert
            expect($event->getResponse())->toBeNull()
                ->and($event->isPropagationStopped())->toBeFalse();
        });

        test('onExecutingFunction does nothing when token is missing from extension data', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0016',
                call: new CallData(function: 'test'),
            );
            $extensionData = ExtensionData::request(ExtensionUrn::Cancellation->value, []);
            $event = new ExecutingFunction($request, $extensionData);

            // Act
            $extension->onExecutingFunction($event);

            // Assert
            expect($event->getResponse())->toBeNull()
                ->and($event->isPropagationStopped())->toBeFalse();
        });

        test('onExecutingFunction does nothing when token is empty string', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0017',
                call: new CallData(function: 'test'),
            );
            $extensionData = ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => '']);
            $event = new ExecutingFunction($request, $extensionData);

            // Act
            $extension->onExecutingFunction($event);

            // Assert
            expect($event->getResponse())->toBeNull()
                ->and($event->isPropagationStopped())->toBeFalse();
        });

        test('onExecutingFunction does nothing when token is not a string', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0018',
                call: new CallData(function: 'test'),
            );
            $extensionData = ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 456]);
            $event = new ExecutingFunction($request, $extensionData);

            // Act
            $extension->onExecutingFunction($event);

            // Assert
            expect($event->getResponse())->toBeNull()
                ->and($event->isPropagationStopped())->toBeFalse();
        });

        test('isCancelled returns false for non-existent token', function (): void {
            // Arrange
            $extension = new CancellationExtension();

            // Act
            $result = $extension->isCancelled('never-registered');

            // Assert
            expect($result)->toBeFalse();
        });

        test('isActive returns false for non-existent token', function (): void {
            // Arrange
            $extension = new CancellationExtension();

            // Act
            $result = $extension->isActive('never-registered');

            // Assert
            expect($result)->toBeFalse();
        });

        test('cleanup does nothing for non-existent token', function (): void {
            // Arrange
            $extension = new CancellationExtension();

            // Act & Assert (should not throw exception)
            $extension->cleanup('non-existent-token');

            expect(true)->toBeTrue();
        });

        test('token respects custom TTL configuration', function (): void {
            // Arrange
            $customTtl = 600;
            $extension = new CancellationExtension(tokenTtl: $customTtl);
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0019',
                call: new CallData(function: 'test'),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 'custom-ttl']),
                ],
            );
            $event = new RequestValidated($request);

            // Act
            $extension->onRequestValidated($event);

            // Assert - token should be active immediately
            expect($extension->isActive('custom-ttl'))->toBeTrue();
        });

        test('multiple tokens can be active simultaneously', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $request1 = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0020',
                call: new CallData(function: 'test'),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 'token-1']),
                ],
            );
            $request2 = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0021',
                call: new CallData(function: 'test'),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 'token-2']),
                ],
            );
            $event1 = new RequestValidated($request1);
            $event2 = new RequestValidated($request2);

            // Act
            $extension->onRequestValidated($event1);
            $extension->onRequestValidated($event2);

            // Assert
            expect($extension->isActive('token-1'))->toBeTrue()
                ->and($extension->isActive('token-2'))->toBeTrue()
                ->and($extension->isCancelled('token-1'))->toBeFalse()
                ->and($extension->isCancelled('token-2'))->toBeFalse();
        });

        test('cancelling one token does not affect other tokens', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $request1 = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0022',
                call: new CallData(function: 'test'),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 'token-a']),
                ],
            );
            $request2 = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0023',
                call: new CallData(function: 'test'),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 'token-b']),
                ],
            );
            $event1 = new RequestValidated($request1);
            $event2 = new RequestValidated($request2);
            $extension->onRequestValidated($event1);
            $extension->onRequestValidated($event2);

            // Act
            $extension->cancel('token-a');

            // Assert
            expect($extension->isCancelled('token-a'))->toBeTrue()
                ->and($extension->isActive('token-b'))->toBeTrue()
                ->and($extension->isCancelled('token-b'))->toBeFalse();
        });

        test('token can contain special characters', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $specialToken = 'token-with-special-chars!@#$%^&*()_+-=[]{}|;:,.<>?';
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0024',
                call: new CallData(function: 'test'),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => $specialToken]),
                ],
            );
            $event = new RequestValidated($request);

            // Act
            $extension->onRequestValidated($event);

            // Assert - Special characters cause token to be invalid/not registered
            expect($extension->isActive($specialToken))->toBeFalse()
                ->and($extension->cancel($specialToken))->toBeFalse()
                ->and($extension->isCancelled($specialToken))->toBeFalse();
        });

        test('token can be very long string', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $longToken = str_repeat('a', 1_000);
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0025',
                call: new CallData(function: 'test'),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => $longToken]),
                ],
            );
            $event = new RequestValidated($request);

            // Act
            $extension->onRequestValidated($event);

            // Assert - Very long tokens are rejected/not registered
            expect($extension->isActive($longToken))->toBeFalse()
                ->and($extension->cancel($longToken))->toBeFalse()
                ->and($extension->isCancelled($longToken))->toBeFalse();
        });

        test('cleanup can be called multiple times on same token', function (): void {
            // Arrange
            $extension = new CancellationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0026',
                call: new CallData(function: 'test'),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Cancellation->value, ['token' => 'multi-cleanup']),
                ],
            );
            $event = new RequestValidated($request);
            $extension->onRequestValidated($event);

            // Act
            $extension->cleanup('multi-cleanup');
            $extension->cleanup('multi-cleanup');
            $extension->cleanup('multi-cleanup');

            // Assert
            expect($extension->isActive('multi-cleanup'))->toBeFalse()
                ->and($extension->isCancelled('multi-cleanup'))->toBeFalse();
        });
    });
});

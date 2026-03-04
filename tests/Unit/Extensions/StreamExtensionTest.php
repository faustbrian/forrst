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
use Cline\Forrst\Events\ExecutingFunction;
use Cline\Forrst\Events\RequestValidated;
use Cline\Forrst\Extensions\ExtensionUrn;
use Cline\Forrst\Extensions\StreamExtension;

describe('StreamExtension', function (): void {
    beforeEach(function (): void {
        // Clear any existing stream context
        if (!function_exists('request')) {
            return;
        }

        request()->attributes->remove(StreamExtension::CONTEXT_KEY);
    });

    describe('Happy Paths', function (): void {
        test('returns correct URN constant', function (): void {
            // Arrange
            $extension = new StreamExtension();

            // Act
            $urn = $extension->getUrn();

            // Assert
            expect($urn)->toBe(ExtensionUrn::Stream->value);
            expect($urn)->toBe('urn:cline:forrst:ext:stream');
        });

        test('returns subscribed events', function (): void {
            // Arrange
            $extension = new StreamExtension();

            // Act
            $events = $extension->getSubscribedEvents();

            // Assert
            expect($events)->toHaveKey(RequestValidated::class);
            expect($events)->toHaveKey(ExecutingFunction::class);
        });

        test('toCapabilities returns stream capability info', function (): void {
            // Arrange
            $extension = new StreamExtension();

            // Act
            $capabilities = $extension->toCapabilities();

            // Assert
            expect($capabilities)->toHaveKey('urn');
            expect($capabilities['urn'])->toBe(ExtensionUrn::Stream->value);
            expect($capabilities)->toHaveKey('content_type');
            expect($capabilities['content_type'])->toBe('text/event-stream');
            expect($capabilities)->toHaveKey('events');
            expect($capabilities['events'])->toBe(['data', 'progress', 'error', 'done']);
        });

        test('shouldStream returns false when no context set', function (): void {
            // Arrange - ensure no context
            request()->attributes->remove(StreamExtension::CONTEXT_KEY);

            // Act
            $result = StreamExtension::shouldStream();

            // Assert
            expect($result)->toBeFalse();
        });

        test('getContext returns null when no context set', function (): void {
            // Arrange - ensure no context
            request()->attributes->remove(StreamExtension::CONTEXT_KEY);

            // Act
            $result = StreamExtension::getContext();

            // Assert
            expect($result)->toBeNull();
        });

        test('onRequestValidated ignores requests without stream extension', function (): void {
            // Arrange
            $extension = new StreamExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'req_123',
                call: new CallData(
                    function: 'urn:cline:forrst:fn:test:function',
                    version: '1.0.0',
                    arguments: [],
                ),
                extensions: [],
            );
            $event = new RequestValidated($request);

            // Act
            $extension->onRequestValidated($event);

            // Assert
            expect($event->getResponse())->toBeNull();
            expect($event->isPropagationStopped())->toBeFalse();
        });

        test('onRequestValidated ignores requests with accept false', function (): void {
            // Arrange
            $extension = new StreamExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'req_123',
                call: new CallData(
                    function: 'urn:cline:forrst:fn:test:function',
                    version: '1.0.0',
                    arguments: [],
                ),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Stream->value, ['accept' => false]),
                ],
            );
            $event = new RequestValidated($request);

            // Act
            $extension->onRequestValidated($event);

            // Assert
            expect($event->getResponse())->toBeNull();
            expect($event->isPropagationStopped())->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('getContext returns null for invalid context structure', function (): void {
            // Arrange - set invalid context
            request()->attributes->set(StreamExtension::CONTEXT_KEY, 'invalid');

            // Act
            $result = StreamExtension::getContext();

            // Assert
            expect($result)->toBeNull();
        });

        test('getContext returns null for incomplete context array', function (): void {
            // Arrange - set incomplete context
            request()->attributes->set(StreamExtension::CONTEXT_KEY, ['enabled' => true]);

            // Act
            $result = StreamExtension::getContext();

            // Assert
            expect($result)->toBeNull();
        });

        test('shouldStream returns false for non-array context', function (): void {
            // Arrange - set non-array context
            request()->attributes->set(StreamExtension::CONTEXT_KEY, 'not an array');

            // Act
            $result = StreamExtension::shouldStream();

            // Assert
            expect($result)->toBeFalse();
        });

        test('shouldStream returns false when enabled is not true', function (): void {
            // Arrange - set context with enabled = false
            request()->attributes->set(StreamExtension::CONTEXT_KEY, ['enabled' => false]);

            // Act
            $result = StreamExtension::shouldStream();

            // Assert
            expect($result)->toBeFalse();
        });

        test('CONTEXT_KEY constant has correct value', function (): void {
            expect(StreamExtension::CONTEXT_KEY)->toBe('forrst.stream');
        });

        test('onRequestValidated ignores requests with accept not strictly true', function (): void {
            // Arrange
            $extension = new StreamExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'req_123',
                call: new CallData(
                    function: 'urn:cline:forrst:fn:test:function',
                    version: '1.0.0',
                    arguments: [],
                ),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Stream->value, ['accept' => 'true']),
                ],
            );
            $event = new RequestValidated($request);

            // Act
            $extension->onRequestValidated($event);

            // Assert
            expect($event->getResponse())->toBeNull();
            expect($event->isPropagationStopped())->toBeFalse();
        });

        test('onRequestValidated ignores requests with missing accept option', function (): void {
            // Arrange
            $extension = new StreamExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'req_123',
                call: new CallData(
                    function: 'urn:cline:forrst:fn:test:function',
                    version: '1.0.0',
                    arguments: [],
                ),
                extensions: [
                    ExtensionData::request(ExtensionUrn::Stream->value, []),
                ],
            );
            $event = new RequestValidated($request);

            // Act
            $extension->onRequestValidated($event);

            // Assert
            expect($event->getResponse())->toBeNull();
            expect($event->isPropagationStopped())->toBeFalse();
        });
    });
});

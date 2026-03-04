<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Events\ExecutingFunction;
use Cline\Forrst\Events\FunctionExecuted;
use Cline\Forrst\Events\RequestValidated;
use Cline\Forrst\Events\SendingResponse;
use Cline\Forrst\Extensions\ExtensionEventSubscriber;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;

describe('ExtensionEventSubscriber', function (): void {
    describe('Happy Paths', function (): void {
        test('subscribes to all extension events', function (): void {
            // Arrange
            $subscriber = new ExtensionEventSubscriber();
            $events = Mockery::mock(Dispatcher::class);

            // Assert - verify all 4 event types are subscribed
            $events->shouldReceive('listen')
                ->with(RequestValidated::class, Mockery::type(Closure::class))
                ->once();
            $events->shouldReceive('listen')
                ->with(ExecutingFunction::class, Mockery::type(Closure::class))
                ->once();
            $events->shouldReceive('listen')
                ->with(FunctionExecuted::class, Mockery::type(Closure::class))
                ->once();
            $events->shouldReceive('listen')
                ->with(SendingResponse::class, Mockery::type(Closure::class))
                ->once();

            // Act
            $subscriber->subscribe($events);
        });

        test('events are dispatched through Laravel event system', function (): void {
            // Arrange
            Event::fake([
                RequestValidated::class,
                ExecutingFunction::class,
                FunctionExecuted::class,
                SendingResponse::class,
            ]);

            $request = RequestObjectData::from([
                'protocol' => ['name' => 'forrst', 'version' => '1.0'],
                'id' => '01JTEST0001',
                'call' => ['function' => 'test.method'],
            ]);

            // Act
            event(
                new RequestValidated($request),
            );

            // Assert
            Event::assertDispatched(RequestValidated::class, fn ($event): bool => $event->request->id === $request->id);
        });

        test('ExecutingFunction carries extension data', function (): void {
            // Arrange
            Event::fake([ExecutingFunction::class]);

            $request = RequestObjectData::from([
                'protocol' => ['name' => 'forrst', 'version' => '1.0'],
                'id' => '01JTEST0002',
                'call' => ['function' => 'test.method'],
            ]);
            $extension = ExtensionData::request('urn:cline:forrst:ext:test', ['key' => 'value']);

            // Act
            event(
                new ExecutingFunction($request, $extension),
            );

            // Assert
            Event::assertDispatched(ExecutingFunction::class, fn ($event): bool => $event->extension->urn === 'urn:cline:forrst:ext:test'
                && $event->extension->options === ['key' => 'value']);
        });

        test('FunctionExecuted carries response data', function (): void {
            // Arrange
            Event::fake([FunctionExecuted::class]);

            $request = RequestObjectData::from([
                'protocol' => ['name' => 'forrst', 'version' => '1.0'],
                'id' => '01JTEST0003',
                'call' => ['function' => 'test.method'],
            ]);
            $extension = ExtensionData::request('urn:cline:forrst:ext:test');
            $response = ResponseData::success(['result' => 'ok'], '01JTEST0003');

            // Act
            event(
                new FunctionExecuted($request, $extension, $response),
            );

            // Assert
            Event::assertDispatched(FunctionExecuted::class, fn ($event): bool => $event->getResponse()->result === ['result' => 'ok']);
        });

        test('SendingResponse is final event before serialization', function (): void {
            // Arrange
            Event::fake([SendingResponse::class]);

            $request = RequestObjectData::from([
                'protocol' => ['name' => 'forrst', 'version' => '1.0'],
                'id' => '01JTEST0004',
                'call' => ['function' => 'test.method'],
            ]);
            $response = ResponseData::success(['final' => true], '01JTEST0004');

            // Act
            event(
                new SendingResponse($request, $response),
            );

            // Assert
            Event::assertDispatched(SendingResponse::class, fn ($event): bool => $event->getResponse()->result === ['final' => true]);
        });

        test('rebuild clears listener cache', function (): void {
            // Arrange
            $subscriber = new ExtensionEventSubscriber();

            // Act - should not throw
            $subscriber->rebuild();

            // Assert - subscriber can still be used after rebuild
            $events = Mockery::mock(Dispatcher::class);
            $events->shouldReceive('listen')->times(4);
            $subscriber->subscribe($events);
        });
    });

    describe('Edge Cases', function (): void {
        test('event propagation can be stopped', function (): void {
            // Arrange
            $request = RequestObjectData::from([
                'protocol' => ['name' => 'forrst', 'version' => '1.0'],
                'id' => '01JTEST0005',
                'call' => ['function' => 'test.method'],
            ]);
            $event = new RequestValidated($request);

            // Act
            $event->stopPropagation();

            // Assert
            expect($event->isPropagationStopped())->toBeTrue();
        });

        test('event can set short-circuit response', function (): void {
            // Arrange
            $request = RequestObjectData::from([
                'protocol' => ['name' => 'forrst', 'version' => '1.0'],
                'id' => '01JTEST0006',
                'call' => ['function' => 'test.method'],
            ]);
            $event = new RequestValidated($request);
            $response = ResponseData::success(['cached' => true], '01JTEST0006');

            // Act
            $event->setResponse($response);

            // Assert
            expect($event->getResponse())->toBe($response)
                ->and($event->getResponse()->result)->toBe(['cached' => true]);
        });

        test('FunctionExecuted response is mutable', function (): void {
            // Arrange
            $request = RequestObjectData::from([
                'protocol' => ['name' => 'forrst', 'version' => '1.0'],
                'id' => '01JTEST0007',
                'call' => ['function' => 'test.method'],
            ]);
            $extension = ExtensionData::request('urn:cline:forrst:ext:test');
            $originalResponse = ResponseData::success(['original' => true], '01JTEST0007');
            $event = new FunctionExecuted($request, $extension, $originalResponse);

            // Act
            $modifiedResponse = ResponseData::success(['modified' => true], '01JTEST0007');
            $event->setResponse($modifiedResponse);

            // Assert
            expect($event->getResponse()->result)->toBe(['modified' => true]);
        });

        test('SendingResponse response is mutable', function (): void {
            // Arrange
            $request = RequestObjectData::from([
                'protocol' => ['name' => 'forrst', 'version' => '1.0'],
                'id' => '01JTEST0008',
                'call' => ['function' => 'test.method'],
            ]);
            $originalResponse = ResponseData::success(['original' => true], '01JTEST0008');
            $event = new SendingResponse($request, $originalResponse);

            // Act
            $modifiedResponse = ResponseData::success(['final_modified' => true], '01JTEST0008');
            $event->setResponse($modifiedResponse);

            // Assert
            expect($event->getResponse()->result)->toBe(['final_modified' => true]);
        });

        test('events carry request context through lifecycle', function (): void {
            // Arrange
            $request = RequestObjectData::from([
                'protocol' => ['name' => 'forrst', 'version' => '1.0'],
                'id' => '01JTEST0009',
                'call' => ['function' => 'user.get', 'arguments' => ['id' => 123]],
            ]);

            // Act
            $validatedEvent = new RequestValidated($request);
            $executingEvent = new ExecutingFunction($request, ExtensionData::request('urn:cline:forrst:ext:test'));
            $response = ResponseData::success(['user' => 'data'], '01JTEST0009');
            $executedEvent = new FunctionExecuted($request, ExtensionData::request('urn:cline:forrst:ext:test'), $response);
            $sendingEvent = new SendingResponse($request, $response);

            // Assert - all events reference same request
            expect($validatedEvent->request->id)->toBe('01JTEST0009')
                ->and($executingEvent->request->id)->toBe('01JTEST0009')
                ->and($executedEvent->request->id)->toBe('01JTEST0009')
                ->and($sendingEvent->request->id)->toBe('01JTEST0009');
        });
    });

    describe('Sad Paths', function (): void {
        test('getResponse returns null when not set', function (): void {
            // Arrange
            $request = RequestObjectData::from([
                'protocol' => ['name' => 'forrst', 'version' => '1.0'],
                'id' => '01JTEST0010',
                'call' => ['function' => 'test.method'],
            ]);
            $event = new RequestValidated($request);

            // Act & Assert
            expect($event->getResponse())->toBeNull();
        });

        test('propagation is not stopped by default', function (): void {
            // Arrange
            $request = RequestObjectData::from([
                'protocol' => ['name' => 'forrst', 'version' => '1.0'],
                'id' => '01JTEST0011',
                'call' => ['function' => 'test.method'],
            ]);
            $event = new RequestValidated($request);

            // Act & Assert
            expect($event->isPropagationStopped())->toBeFalse();
        });
    });
});

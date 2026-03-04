<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Contracts\ExtensionInterface;
use Cline\Forrst\Events\ExecutingFunction;
use Cline\Forrst\Events\FunctionExecuted;
use Cline\Forrst\Events\RequestValidated;
use Cline\Forrst\Events\SendingResponse;
use Cline\Forrst\Extensions\AbstractExtension;
use Tests\Unit\Extensions\Fixtures\ConcreteExtension;

describe('AbstractExtension', function (): void {
    describe('Happy Paths', function (): void {
        test('implements ExtensionInterface contract', function (): void {
            // Arrange
            $extension = new ConcreteExtension();

            // Act & Assert
            expect($extension)->toBeInstanceOf(ExtensionInterface::class)
                ->and($extension)->toBeInstanceOf(AbstractExtension::class);
        });

        test('isGlobal returns false by default for opt-in behavior', function (): void {
            // Arrange
            $extension = new ConcreteExtension();

            // Act
            $isGlobal = $extension->isGlobal();

            // Assert
            expect($isGlobal)->toBeFalse();
        });

        test('isErrorFatal returns true by default for fatal error handling', function (): void {
            // Arrange
            $extension = new ConcreteExtension();

            // Act
            $isFatal = $extension->isErrorFatal();

            // Assert
            expect($isFatal)->toBeTrue();
        });

        test('getSubscribedEvents returns empty array by default', function (): void {
            // Arrange
            $extension = new ConcreteExtension();

            // Act
            $events = $extension->getSubscribedEvents();

            // Assert
            expect($events)->toBeArray()
                ->and($events)->toBeEmpty()
                ->and($events)->toHaveCount(0);
        });

        test('toCapabilities returns URN in capabilities format', function (): void {
            // Arrange
            $extension = new ConcreteExtension();

            // Act
            $capabilities = $extension->toCapabilities();

            // Assert
            expect($capabilities)->toBeArray()
                ->and($capabilities)->toHaveKey('urn')
                ->and($capabilities['urn'])->toBe('urn:test:extension:concrete');
        });

        test('toCapabilities array contains only URN by default', function (): void {
            // Arrange
            $extension = new ConcreteExtension();

            // Act
            $capabilities = $extension->toCapabilities();

            // Assert
            expect($capabilities)->toHaveCount(1)
                ->and($capabilities)->toHaveKeys(['urn'])
                ->and($capabilities)->not->toHaveKey('documentation');
        });

        test('getUrn is called by toCapabilities method', function (): void {
            // Arrange
            $extension = new ConcreteExtension();

            // Act
            $capabilities = $extension->toCapabilities();

            // Assert
            expect($capabilities['urn'])->toBe($extension->getUrn())
                ->and($extension->getUrn())->toBeString()
                ->and($extension->getUrn())->not->toBeEmpty();
        });
    });

    describe('Sad Paths', function (): void {
        test('getSubscribedEvents does not subscribe to RequestValidated by default', function (): void {
            // Arrange
            $extension = new ConcreteExtension();

            // Act
            $events = $extension->getSubscribedEvents();

            // Assert
            expect($events)->not->toHaveKey(RequestValidated::class);
        });

        test('getSubscribedEvents does not subscribe to ExecutingFunction by default', function (): void {
            // Arrange
            $extension = new ConcreteExtension();

            // Act
            $events = $extension->getSubscribedEvents();

            // Assert
            expect($events)->not->toHaveKey(ExecutingFunction::class);
        });

        test('getSubscribedEvents does not subscribe to FunctionExecuted by default', function (): void {
            // Arrange
            $extension = new ConcreteExtension();

            // Act
            $events = $extension->getSubscribedEvents();

            // Assert
            expect($events)->not->toHaveKey(FunctionExecuted::class);
        });

        test('getSubscribedEvents does not subscribe to SendingResponse by default', function (): void {
            // Arrange
            $extension = new ConcreteExtension();

            // Act
            $events = $extension->getSubscribedEvents();

            // Assert
            expect($events)->not->toHaveKey(SendingResponse::class);
        });
    });

    describe('Edge Cases', function (): void {
        test('concrete extension can override isGlobal to return true', function (): void {
            // Arrange
            $extension = new class() extends AbstractExtension
            {
                public function getUrn(): string
                {
                    return 'urn:test:global';
                }

                public function isGlobal(): bool
                {
                    return true;
                }
            };

            // Act
            $isGlobal = $extension->isGlobal();

            // Assert
            expect($isGlobal)->toBeTrue();
        });

        test('concrete extension can override isErrorFatal to return false', function (): void {
            // Arrange
            $extension = new class() extends AbstractExtension
            {
                public function getUrn(): string
                {
                    return 'urn:test:nonfatal';
                }

                public function isErrorFatal(): bool
                {
                    return false;
                }
            };

            // Act
            $isFatal = $extension->isErrorFatal();

            // Assert
            expect($isFatal)->toBeFalse();
        });

        test('concrete extension can override getSubscribedEvents with custom subscriptions', function (): void {
            // Arrange
            $extension = new class() extends AbstractExtension
            {
                public function getUrn(): string
                {
                    return 'urn:test:events';
                }

                public function getSubscribedEvents(): array
                {
                    return [
                        RequestValidated::class => [
                            'priority' => 10,
                            'method' => 'onRequestValidated',
                        ],
                        ExecutingFunction::class => [
                            'priority' => 20,
                            'method' => 'onExecutingFunction',
                        ],
                    ];
                }
            };

            // Act
            $events = $extension->getSubscribedEvents();

            // Assert
            expect($events)->toHaveCount(2)
                ->and($events)->toHaveKey(RequestValidated::class)
                ->and($events)->toHaveKey(ExecutingFunction::class)
                ->and($events[RequestValidated::class])->toHaveKeys(['priority', 'method'])
                ->and($events[RequestValidated::class]['priority'])->toBe(10)
                ->and($events[RequestValidated::class]['method'])->toBe('onRequestValidated');
        });

        test('concrete extension can override getCapabilityMetadata to include documentation URL', function (): void {
            // Arrange
            $extension = new class() extends AbstractExtension
            {
                public function getUrn(): string
                {
                    return 'urn:test:docs';
                }

                protected function getCapabilityMetadata(): array
                {
                    return [
                        'documentation' => 'https://docs.example.com/extensions/test',
                    ];
                }
            };

            // Act
            $capabilities = $extension->toCapabilities();

            // Assert
            expect($capabilities)->toHaveCount(2)
                ->and($capabilities)->toHaveKey('urn')
                ->and($capabilities)->toHaveKey('documentation')
                ->and($capabilities['documentation'])->toBe('https://docs.example.com/extensions/test');
        });

        test('toCapabilities uses getUrn method consistently', function (): void {
            // Arrange
            $extension = new class() extends AbstractExtension
            {
                private int $counter = 0;

                public function getUrn(): string
                {
                    ++$this->counter;

                    return 'urn:test:counter:'.$this->counter;
                }
            };

            // Act
            $capabilities = $extension->toCapabilities();

            // Assert - toCapabilities should call getUrn exactly once
            expect($capabilities['urn'])->toBe('urn:test:counter:1');
        });

        test('multiple instances maintain independent state', function (): void {
            // Arrange
            $extension1 = new ConcreteExtension();
            $extension2 = new ConcreteExtension();

            // Act
            $global1 = $extension1->isGlobal();
            $global2 = $extension2->isGlobal();
            $fatal1 = $extension1->isErrorFatal();
            $fatal2 = $extension2->isErrorFatal();

            // Assert
            expect($global1)->toBe($global2)
                ->and($fatal1)->toBe($fatal2)
                ->and($extension1)->not->toBe($extension2);
        });
    });
});

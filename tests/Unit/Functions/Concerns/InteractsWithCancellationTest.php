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
use Cline\Forrst\Exceptions\CancelledException;
use Cline\Forrst\Extensions\Cancellation\CancellationExtension;
use Cline\Forrst\Extensions\ExtensionUrn;
use Illuminate\Support\Facades\Cache;
use Tests\Unit\Functions\Concerns\Fixtures\TestClassWithCancellation;

describe('InteractsWithCancellation', function (): void {
    beforeEach(function (): void {
        // Clear cache before each test
        Cache::flush();

        // Register CancellationExtension in the container
        app()->singleton(CancellationExtension::class, fn (): CancellationExtension => new CancellationExtension());
    });

    describe('getCancellationToken', function (): void {
        describe('Happy Paths', function (): void {
            test('extracts token from request with cancellation extension', function (): void {
                // Arrange
                $cancellationToken = 'test-cancel-token-123';
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: ExtensionUrn::Cancellation,
                            options: ['token' => $cancellationToken],
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Act
                $result = $testClass->exposeGetCancellationToken();

                // Assert
                expect($result)->toBe($cancellationToken);
            });

            test('extracts token when extension uses string URN instead of enum', function (): void {
                // Arrange
                $cancellationToken = 'token-with-string-urn';
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: 'urn:cline:forrst:ext:cancellation',
                            options: ['token' => $cancellationToken],
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Act
                $result = $testClass->exposeGetCancellationToken();

                // Assert
                expect($result)->toBe($cancellationToken);
            });
        });

        describe('Sad Paths', function (): void {
            test('returns null when request has no extensions', function (): void {
                // Arrange
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: null,
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Act
                $result = $testClass->exposeGetCancellationToken();

                // Assert
                expect($result)->toBeNull();
            });

            test('returns null when cancellation extension is not present', function (): void {
                // Arrange
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: ExtensionUrn::Async,
                            options: ['timeout' => 5_000],
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Act
                $result = $testClass->exposeGetCancellationToken();

                // Assert
                expect($result)->toBeNull();
            });

            test('returns null when cancellation extension has no token option', function (): void {
                // Arrange
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: ExtensionUrn::Cancellation,
                            options: [],
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Act
                $result = $testClass->exposeGetCancellationToken();

                // Assert
                expect($result)->toBeNull();
            });

            test('returns null when token option is not a string', function (): void {
                // Arrange
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: ExtensionUrn::Cancellation,
                            options: ['token' => 12_345], // Non-string token
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Act
                $result = $testClass->exposeGetCancellationToken();

                // Assert
                expect($result)->toBeNull();
            });
        });

        describe('Edge Cases', function (): void {
            test('returns null when token option is empty string', function (): void {
                // Arrange
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: ExtensionUrn::Cancellation,
                            options: ['token' => ''],
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Act
                $result = $testClass->exposeGetCancellationToken();

                // Assert
                expect($result)->toBe('');
            });

            test('extracts token when multiple extensions are present', function (): void {
                // Arrange
                $cancellationToken = 'multi-ext-token';
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: ExtensionUrn::Async,
                            options: ['timeout' => 5_000],
                        ),
                        new ExtensionData(
                            urn: ExtensionUrn::Cancellation,
                            options: ['token' => $cancellationToken],
                        ),
                        new ExtensionData(
                            urn: ExtensionUrn::Tracing,
                            options: ['trace_id' => 'abc123'],
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Act
                $result = $testClass->exposeGetCancellationToken();

                // Assert
                expect($result)->toBe($cancellationToken);
            });

            test('returns null when token option is null', function (): void {
                // Arrange
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: ExtensionUrn::Cancellation,
                            options: ['token' => null],
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Act
                $result = $testClass->exposeGetCancellationToken();

                // Assert
                expect($result)->toBeNull();
            });
        });
    });

    describe('isCancellationRequested', function (): void {
        describe('Happy Paths', function (): void {
            test('returns true when token is cancelled', function (): void {
                // Arrange
                $cancellationToken = 'cancelled-token-123';
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: ExtensionUrn::Cancellation,
                            options: ['token' => $cancellationToken],
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Mark token as cancelled
                Cache::put('forrst:cancel:'.$cancellationToken, 'cancelled', 300);

                // Act
                $result = $testClass->exposeIsCancellationRequested();

                // Assert
                expect($result)->toBeTrue();
            });

            test('returns false when token is active but not cancelled', function (): void {
                // Arrange
                $cancellationToken = 'active-token-456';
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: ExtensionUrn::Cancellation,
                            options: ['token' => $cancellationToken],
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Mark token as active (not cancelled)
                Cache::put('forrst:cancel:'.$cancellationToken, 'active', 300);

                // Act
                $result = $testClass->exposeIsCancellationRequested();

                // Assert
                expect($result)->toBeFalse();
            });
        });

        describe('Sad Paths', function (): void {
            test('returns false when request has no cancellation token', function (): void {
                // Arrange
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: null,
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Act
                $result = $testClass->exposeIsCancellationRequested();

                // Assert
                expect($result)->toBeFalse();
            });

            test('returns false when token is not in cache', function (): void {
                // Arrange
                $cancellationToken = 'non-existent-token';
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: ExtensionUrn::Cancellation,
                            options: ['token' => $cancellationToken],
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Act
                $result = $testClass->exposeIsCancellationRequested();

                // Assert
                expect($result)->toBeFalse();
            });
        });

        describe('Edge Cases', function (): void {
            test('returns false when token exists with unexpected cache value', function (): void {
                // Arrange
                $cancellationToken = 'weird-value-token';
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: ExtensionUrn::Cancellation,
                            options: ['token' => $cancellationToken],
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Set unexpected cache value
                Cache::put('forrst:cancel:'.$cancellationToken, 'unknown-status', 300);

                // Act
                $result = $testClass->exposeIsCancellationRequested();

                // Assert
                expect($result)->toBeFalse();
            });

            test('returns false when cancellation extension has empty token', function (): void {
                // Arrange
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: ExtensionUrn::Cancellation,
                            options: ['token' => ''],
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Act
                $result = $testClass->exposeIsCancellationRequested();

                // Assert
                expect($result)->toBeFalse();
            });

            test('checks cancellation status multiple times correctly', function (): void {
                // Arrange
                $cancellationToken = 'multi-check-token';
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: ExtensionUrn::Cancellation,
                            options: ['token' => $cancellationToken],
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Initially active
                Cache::put('forrst:cancel:'.$cancellationToken, 'active', 300);

                // Act & Assert - first check
                expect($testClass->exposeIsCancellationRequested())->toBeFalse();

                // Act - mark as cancelled
                Cache::put('forrst:cancel:'.$cancellationToken, 'cancelled', 300);

                // Act & Assert - second check
                expect($testClass->exposeIsCancellationRequested())->toBeTrue();

                // Act & Assert - third check (still cancelled)
                expect($testClass->exposeIsCancellationRequested())->toBeTrue();
            });
        });
    });

    describe('throwIfCancellationRequested', function (): void {
        describe('Happy Paths', function (): void {
            test('does not throw when token is not cancelled', function (): void {
                // Arrange
                $cancellationToken = 'active-no-throw-token';
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: ExtensionUrn::Cancellation,
                            options: ['token' => $cancellationToken],
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Mark token as active
                Cache::put('forrst:cancel:'.$cancellationToken, 'active', 300);

                // Act & Assert
                expect(fn () => $testClass->exposeThrowIfCancellationRequested())
                    ->not->toThrow(CancelledException::class);
            });

            test('does not throw when request has no cancellation extension', function (): void {
                // Arrange
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: null,
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Act & Assert
                expect(fn () => $testClass->exposeThrowIfCancellationRequested())
                    ->not->toThrow(CancelledException::class);
            });
        });

        describe('Sad Paths', function (): void {
            test('throws CancelledException when token is cancelled', function (): void {
                // Arrange
                $cancellationToken = 'cancelled-throw-token';
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: ExtensionUrn::Cancellation,
                            options: ['token' => $cancellationToken],
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Mark token as cancelled
                Cache::put('forrst:cancel:'.$cancellationToken, 'cancelled', 300);

                // Act & Assert
                expect(fn () => $testClass->exposeThrowIfCancellationRequested())
                    ->toThrow(CancelledException::class);
            });
        });

        describe('Edge Cases', function (): void {
            test('does not throw when token is not in cache', function (): void {
                // Arrange
                $cancellationToken = 'non-existent-throw-token';
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: ExtensionUrn::Cancellation,
                            options: ['token' => $cancellationToken],
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Act & Assert
                expect(fn () => $testClass->exposeThrowIfCancellationRequested())
                    ->not->toThrow(CancelledException::class);
            });

            test('throws exception that can be caught and inspected', function (): void {
                // Arrange
                $cancellationToken = 'inspect-exception-token';
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: ExtensionUrn::Cancellation,
                            options: ['token' => $cancellationToken],
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Mark token as cancelled
                Cache::put('forrst:cancel:'.$cancellationToken, 'cancelled', 300);

                // Act & Assert
                try {
                    $testClass->exposeThrowIfCancellationRequested();
                    $this->fail('Expected CancelledException to be thrown');
                } catch (CancelledException $cancelledException) {
                    expect($cancelledException)->toBeInstanceOf(CancelledException::class);
                }
            });

            test('can be called multiple times without throwing if not cancelled', function (): void {
                // Arrange
                $cancellationToken = 'multi-call-no-throw-token';
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: ExtensionUrn::Cancellation,
                            options: ['token' => $cancellationToken],
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Mark token as active
                Cache::put('forrst:cancel:'.$cancellationToken, 'active', 300);

                // Act & Assert - call multiple times
                expect(fn () => $testClass->exposeThrowIfCancellationRequested())
                    ->not->toThrow(CancelledException::class);
                expect(fn () => $testClass->exposeThrowIfCancellationRequested())
                    ->not->toThrow(CancelledException::class);
                expect(fn () => $testClass->exposeThrowIfCancellationRequested())
                    ->not->toThrow(CancelledException::class);
            });

            test('throws immediately when token becomes cancelled mid-execution', function (): void {
                // Arrange
                $cancellationToken = 'mid-execution-cancel-token';
                $requestObject = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request-id',
                    call: new CallData(
                        function: 'urn:cline:forrst:fn:test:function',
                    ),
                    extensions: [
                        new ExtensionData(
                            urn: ExtensionUrn::Cancellation,
                            options: ['token' => $cancellationToken],
                        ),
                    ],
                );

                $testClass = new TestClassWithCancellation($requestObject);

                // Initially active
                Cache::put('forrst:cancel:'.$cancellationToken, 'active', 300);

                // Act & Assert - first call succeeds
                expect(fn () => $testClass->exposeThrowIfCancellationRequested())
                    ->not->toThrow(CancelledException::class);

                // Act - mark as cancelled
                Cache::put('forrst:cancel:'.$cancellationToken, 'cancelled', 300);

                // Act & Assert - second call throws
                expect(fn () => $testClass->exposeThrowIfCancellationRequested())
                    ->toThrow(CancelledException::class);
            });
        });
    });
});

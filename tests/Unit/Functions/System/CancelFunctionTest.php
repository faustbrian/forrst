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
use Cline\Forrst\Exceptions\CancellationTokenMissingException;
use Cline\Forrst\Exceptions\CancellationTokenNotFoundException;
use Cline\Forrst\Extensions\Cancellation\CancellationExtension;
use Cline\Forrst\Extensions\Cancellation\Functions\CancelFunction;
use Illuminate\Support\Facades\Cache;

describe('CancelFunction', function (): void {
    describe('Happy Paths', function (): void {
        describe('__invoke()', function (): void {
            test('cancels request successfully with valid token', function (): void {
                // Arrange
                $token = 'valid-token-12345';
                $extension = new CancellationExtension();

                Cache::shouldReceive('get')
                    ->once()
                    ->with('forrst:cancel:'.$token)
                    ->andReturn('active');

                Cache::shouldReceive('put')
                    ->once()
                    ->with('forrst:cancel:'.$token, 'cancelled', 300)
                    ->andReturn(true);

                $function = new CancelFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:cancellation:fn:cancel',
                        arguments: ['token' => $token],
                    ),
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result)->toHaveKey('cancelled')
                    ->and($result['cancelled'])->toBeTrue()
                    ->and($result)->toHaveKey('token')
                    ->and($result['token'])->toBe($token);
            });

            test('returns correct structure with cancelled true and token value', function (): void {
                // Arrange
                $token = 'abc-def-123';
                $extension = new CancellationExtension();

                Cache::shouldReceive('get')
                    ->with('forrst:cancel:'.$token)
                    ->andReturn('active');

                Cache::shouldReceive('put')
                    ->with('forrst:cancel:'.$token, 'cancelled', 300)
                    ->andReturn(true);

                $function = new CancelFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:cancellation:fn:cancel',
                        arguments: ['token' => $token],
                    ),
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toHaveCount(2)
                    ->and($result)->toHaveKeys(['cancelled', 'token']);
            });
        });

        describe('getName()', function (): void {
            test('returns standard Forrst cancel function name', function (): void {
                // Arrange
                $extension = new CancellationExtension();
                $function = new CancelFunction($extension);

                // Act
                $result = $function->getUrn();

                // Assert
                expect($result)->toBe('urn:cline:forrst:ext:cancellation:fn:cancel');
            });
        });

        describe('getSummary()', function (): void {
            test('returns description of cancel function', function (): void {
                // Arrange
                $extension = new CancellationExtension();
                $function = new CancelFunction($extension);

                // Act
                $result = $function->getSummary();

                // Assert
                expect($result)->toBe('Cancel a request by its cancellation token');
            });
        });

        describe('getArguments()', function (): void {
            test('returns token argument configuration', function (): void {
                // Arrange
                $extension = new CancellationExtension();
                $function = new CancelFunction($extension);

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toHaveCount(1);
            });

            test('token argument is required with string type', function (): void {
                // Arrange
                $extension = new CancellationExtension();
                $function = new CancelFunction($extension);

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result[0])->toBeInstanceOf(ArgumentData::class);
                $argumentArray = $result[0]->toArray();
                expect($argumentArray['name'])->toBe('token')
                    ->and($argumentArray['required'])->toBeTrue()
                    ->and($argumentArray['schema']['type'])->toBe('string')
                    ->and($argumentArray['description'])->toBe('Cancellation token from original request');
            });
        });

        describe('getResult()', function (): void {
            test('returns ResultDescriptorData with cancellation response schema', function (): void {
                // Arrange
                $extension = new CancellationExtension();
                $function = new CancelFunction($extension);

                // Act
                $result = $function->getResult();

                // Assert
                expect($result)->toBeInstanceOf(ResultDescriptorData::class);
            });

            test('schema defines object type with cancelled and token properties', function (): void {
                // Arrange
                $extension = new CancellationExtension();
                $function = new CancelFunction($extension);

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['schema']['type'])->toBe('object')
                    ->and($array['schema']['properties'])->toHaveKey('cancelled')
                    ->and($array['schema']['properties']['cancelled']['type'])->toBe('boolean')
                    ->and($array['schema']['properties'])->toHaveKey('token')
                    ->and($array['schema']['properties']['token']['type'])->toBe('string');
            });

            test('schema requires cancelled and token fields', function (): void {
                // Arrange
                $extension = new CancellationExtension();
                $function = new CancelFunction($extension);

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['schema']['required'])->toContain('cancelled')
                    ->and($array['schema']['required'])->toContain('token');
            });

            test('result description is Cancellation result', function (): void {
                // Arrange
                $extension = new CancellationExtension();
                $function = new CancelFunction($extension);

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['description'])->toBe('Cancellation result');
            });
        });

        describe('getErrors()', function (): void {
            test('returns array of ErrorDefinitionData with two error types', function (): void {
                // Arrange
                $extension = new CancellationExtension();
                $function = new CancelFunction($extension);

                // Act
                $result = $function->getErrors();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toHaveCount(2)
                    ->and($result[0])->toBeInstanceOf(ErrorDefinitionData::class)
                    ->and($result[1])->toBeInstanceOf(ErrorDefinitionData::class);
            });

            test('defines CancellationTokenUnknown error', function (): void {
                // Arrange
                $extension = new CancellationExtension();
                $function = new CancelFunction($extension);

                // Act
                $result = $function->getErrors();

                // Assert
                $error = $result[0]->toArray();
                expect($error['code'])->toBe(ErrorCode::CancellationTokenUnknown->value)
                    ->and($error['message'])->toBe('Unknown cancellation token')
                    ->and($error['description'])->toBe('The specified cancellation token does not exist or has expired');
            });

            test('defines CancellationTooLate error', function (): void {
                // Arrange
                $extension = new CancellationExtension();
                $function = new CancelFunction($extension);

                // Act
                $result = $function->getErrors();

                // Assert
                $error = $result[1]->toArray();
                expect($error['code'])->toBe(ErrorCode::CancellationTooLate->value)
                    ->and($error['message'])->toBe('Request already completed')
                    ->and($error['description'])->toBe('The request has already completed and cannot be cancelled');
            });
        });
    });

    describe('Sad Paths', function (): void {
        describe('__invoke() with missing token', function (): void {
            test('throws CancellationTokenMissingException when token argument is missing', function (): void {
                // Arrange
                $extension = new CancellationExtension();
                $function = new CancelFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:cancellation:fn:cancel',
                        arguments: [],
                    ),
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())
                    ->toThrow(CancellationTokenMissingException::class);
            });

            test('throws CancellationTokenMissingException when token argument is null', function (): void {
                // Arrange
                $extension = new CancellationExtension();
                $function = new CancelFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:cancellation:fn:cancel',
                        arguments: ['token' => null],
                    ),
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())
                    ->toThrow(CancellationTokenMissingException::class);
            });

            test('throws CancellationTokenMissingException when token is empty string', function (): void {
                // Arrange
                $extension = new CancellationExtension();
                $function = new CancelFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:cancellation:fn:cancel',
                        arguments: ['token' => ''],
                    ),
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())
                    ->toThrow(CancellationTokenMissingException::class);
            });

            test('throws CancellationTokenMissingException when token is non-string type', function (): void {
                // Arrange
                $extension = new CancellationExtension();
                $function = new CancelFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:cancellation:fn:cancel',
                        arguments: ['token' => 12_345],
                    ),
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())
                    ->toThrow(CancellationTokenMissingException::class);
            });
        });

        describe('__invoke() with unknown token', function (): void {
            test('throws CancellationTokenNotFoundException when extension cancel returns false', function (): void {
                // Arrange
                $token = 'unknown-token';
                $extension = new CancellationExtension();

                Cache::shouldReceive('get')
                    ->once()
                    ->with('forrst:cancel:'.$token)
                    ->andReturn(null);

                $function = new CancelFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:cancellation:fn:cancel',
                        arguments: ['token' => $token],
                    ),
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())
                    ->toThrow(CancellationTokenNotFoundException::class);
            });

            test('throws CancellationTokenNotFoundException with correct message for expired token', function (): void {
                // Arrange
                $token = 'expired-token';
                $extension = new CancellationExtension();

                Cache::shouldReceive('get')
                    ->with('forrst:cancel:'.$token)
                    ->andReturn(null);

                $function = new CancelFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:cancellation:fn:cancel',
                        arguments: ['token' => $token],
                    ),
                );
                $function->setRequest($request);

                // Act & Assert
                try {
                    $function();
                    expect(false)->toBeTrue(); // Should not reach here
                } catch (CancellationTokenNotFoundException $cancellationTokenNotFoundException) {
                    expect($cancellationTokenNotFoundException->getMessage())->toContain('Unknown cancellation token');
                }
            });
        });
    });

    describe('Edge Cases', function (): void {
        describe('__invoke() with whitespace-only token', function (): void {
            test('processes cancellation successfully when token contains whitespace', function (): void {
                // Arrange
                $token = '   ';
                $extension = new CancellationExtension();

                Cache::shouldReceive('get')
                    ->with('forrst:cancel:'.$token)
                    ->andReturn('active');

                Cache::shouldReceive('put')
                    ->with('forrst:cancel:'.$token, 'cancelled', 300)
                    ->andReturn(true);

                $function = new CancelFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:cancellation:fn:cancel',
                        arguments: ['token' => $token],
                    ),
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result)->toHaveKey('cancelled')
                    ->and($result['cancelled'])->toBeTrue();
            });
        });

        describe('__invoke() with very long token', function (): void {
            test('processes cancellation successfully with very long valid token', function (): void {
                // Arrange
                $token = str_repeat('a', 1_000);
                $extension = new CancellationExtension();

                Cache::shouldReceive('get')
                    ->once()
                    ->with('forrst:cancel:'.$token)
                    ->andReturn('active');

                Cache::shouldReceive('put')
                    ->once()
                    ->with('forrst:cancel:'.$token, 'cancelled', 300)
                    ->andReturn(true);

                $function = new CancelFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:cancellation:fn:cancel',
                        arguments: ['token' => $token],
                    ),
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['cancelled'])->toBeTrue()
                    ->and($result['token'])->toBe($token);
            });
        });

        describe('__invoke() with special characters in token', function (): void {
            test('processes cancellation successfully with special characters in token', function (): void {
                // Arrange
                $token = 'token-with-special-chars-!@#$%^&*()_+={}[]|:;<>?,.~`';
                $extension = new CancellationExtension();

                Cache::shouldReceive('get')
                    ->once()
                    ->with('forrst:cancel:'.$token)
                    ->andReturn('active');

                Cache::shouldReceive('put')
                    ->once()
                    ->with('forrst:cancel:'.$token, 'cancelled', 300)
                    ->andReturn(true);

                $function = new CancelFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:cancellation:fn:cancel',
                        arguments: ['token' => $token],
                    ),
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['cancelled'])->toBeTrue()
                    ->and($result['token'])->toBe($token);
            });
        });

        describe('__invoke() with unicode token', function (): void {
            test('processes cancellation successfully with unicode characters in token', function (): void {
                // Arrange
                $token = 'token-with-unicode-日本語-🚀-émojis';
                $extension = new CancellationExtension();

                Cache::shouldReceive('get')
                    ->once()
                    ->with('forrst:cancel:'.$token)
                    ->andReturn('active');

                Cache::shouldReceive('put')
                    ->once()
                    ->with('forrst:cancel:'.$token, 'cancelled', 300)
                    ->andReturn(true);

                $function = new CancelFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:cancellation:fn:cancel',
                        arguments: ['token' => $token],
                    ),
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['cancelled'])->toBeTrue()
                    ->and($result['token'])->toBe($token);
            });
        });

        describe('__invoke() when token already cancelled', function (): void {
            test('succeeds when cancelling already cancelled token', function (): void {
                // Arrange
                $token = 'already-cancelled-token';
                $extension = new CancellationExtension();

                Cache::shouldReceive('get')
                    ->once()
                    ->with('forrst:cancel:'.$token)
                    ->andReturn('cancelled');

                $function = new CancelFunction($extension);
                $request = new RequestObjectData(
                    protocol: ProtocolData::forrst(),
                    id: 'test-request',
                    call: new CallData(
                        function: 'urn:cline:forrst:ext:cancellation:fn:cancel',
                        arguments: ['token' => $token],
                    ),
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['cancelled'])->toBeTrue()
                    ->and($result['token'])->toBe($token);
            });
        });
    });
});

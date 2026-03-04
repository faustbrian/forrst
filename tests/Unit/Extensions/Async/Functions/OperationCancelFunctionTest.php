<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\Forrst\Contracts\OperationRepositoryInterface;
use Cline\Forrst\Data\OperationData;
use Cline\Forrst\Data\OperationStatus;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Discovery\ArgumentData;
use Cline\Forrst\Discovery\ErrorDefinitionData;
use Cline\Forrst\Discovery\ResultDescriptorData;
use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Exceptions\OperationCannotCancelException;
use Cline\Forrst\Exceptions\OperationNotFoundException;
use Cline\Forrst\Extensions\Async\Functions\OperationCancelFunction;
use Mockery\MockInterface;

describe('OperationCancelFunction', function (): void {
    describe('Happy Paths', function (): void {
        describe('getName()', function (): void {
            test('returns standard Forrst operation cancel function name', function (): void {
                // Arrange
                $repository = mock(OperationRepositoryInterface::class);
                $function = new OperationCancelFunction($repository);

                // Act
                $result = $function->getUrn();

                // Assert
                expect($result)->toBe('urn:cline:forrst:ext:async:fn:cancel');
            });
        });

        describe('getVersion()', function (): void {
            test('returns default version', function (): void {
                // Arrange
                $repository = mock(OperationRepositoryInterface::class);
                $function = new OperationCancelFunction($repository);

                // Act
                $result = $function->getVersion();

                // Assert
                expect($result)->toBe('1.0.0');
            });
        });

        describe('getSummary()', function (): void {
            test('returns description of operation cancel function', function (): void {
                // Arrange
                $repository = mock(OperationRepositoryInterface::class);
                $function = new OperationCancelFunction($repository);

                // Act
                $result = $function->getSummary();

                // Assert
                expect($result)->toBe('Cancel a pending async operation');
            });
        });

        describe('getArguments()', function (): void {
            test('returns operation_id argument', function (): void {
                // Arrange
                $repository = mock(OperationRepositoryInterface::class);
                $function = new OperationCancelFunction($repository);

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toHaveCount(1);
            });

            test('operation_id argument is required string', function (): void {
                // Arrange
                $repository = mock(OperationRepositoryInterface::class);
                $function = new OperationCancelFunction($repository);

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result[0])->toBeInstanceOf(ArgumentData::class)
                    ->and($result[0]->name)->toBe('operation_id')
                    ->and($result[0]->schema['type'])->toBe('string')
                    ->and($result[0]->required)->toBeTrue()
                    ->and($result[0]->description)->toBe('Unique operation identifier');
            });
        });

        describe('getResult()', function (): void {
            test('returns ResultDescriptorData with cancellation response schema', function (): void {
                // Arrange
                $repository = mock(OperationRepositoryInterface::class);
                $function = new OperationCancelFunction($repository);

                // Act
                $result = $function->getResult();

                // Assert
                expect($result)->toBeInstanceOf(ResultDescriptorData::class);

                $array = $result->toArray();
                expect($array)->toHaveKey('schema')
                    ->and($array['schema'])->toHaveKey('type')
                    ->and($array['schema']['type'])->toBe('object')
                    ->and($array)->toHaveKey('description')
                    ->and($array['description'])->toBe('Operation cancellation response');
            });

            test('schema defines status as const cancelled', function (): void {
                // Arrange
                $repository = mock(OperationRepositoryInterface::class);
                $function = new OperationCancelFunction($repository);

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['schema']['properties']['status'])->toHaveKey('const')
                    ->and($array['schema']['properties']['status']['const'])->toBe('cancelled');
            });

            test('schema requires operation_id, status, and cancelled_at', function (): void {
                // Arrange
                $repository = mock(OperationRepositoryInterface::class);
                $function = new OperationCancelFunction($repository);

                // Act
                $result = $function->getResult();

                // Assert
                $array = $result->toArray();
                expect($array['schema'])->toHaveKey('required')
                    ->and($array['schema']['required'])->toContain('operation_id')
                    ->and($array['schema']['required'])->toContain('status')
                    ->and($array['schema']['required'])->toContain('cancelled_at');
            });
        });

        describe('getErrors()', function (): void {
            test('returns both error definitions', function (): void {
                // Arrange
                $repository = mock(OperationRepositoryInterface::class);
                $function = new OperationCancelFunction($repository);

                // Act
                $result = $function->getErrors();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toHaveCount(2);
            });

            test('includes AsyncOperationNotFound error definition', function (): void {
                // Arrange
                $repository = mock(OperationRepositoryInterface::class);
                $function = new OperationCancelFunction($repository);

                // Act
                $result = $function->getErrors();

                // Assert
                expect($result[0])->toBeInstanceOf(ErrorDefinitionData::class)
                    ->and($result[0]->code)->toBe(ErrorCode::AsyncOperationNotFound->value)
                    ->and($result[0]->message)->toBe('Operation not found');
            });

            test('includes AsyncCannotCancel error definition', function (): void {
                // Arrange
                $repository = mock(OperationRepositoryInterface::class);
                $function = new OperationCancelFunction($repository);

                // Act
                $result = $function->getErrors();

                // Assert
                expect($result[1])->toBeInstanceOf(ErrorDefinitionData::class)
                    ->and($result[1]->code)->toBe(ErrorCode::AsyncCannotCancel->value)
                    ->and($result[1]->message)->toBe('Operation cannot be cancelled');
            });
        });

        describe('__invoke()', function (): void {
            test('cancels pending operation', function (): void {
                // Arrange
                $operation = new OperationData(
                    'op_000000000000000000000123',
                    'long.running.task',
                    status: OperationStatus::Pending,
                    startedAt: CarbonImmutable::now(),
                );

                $repository = mock(OperationRepositoryInterface::class, function (MockInterface $mock) use ($operation): void {
                    $mock->shouldReceive('find')->with('op_000000000000000000000123', null)->andReturn($operation);
                    $mock->shouldReceive('saveIfVersionMatches')->once()->with(Mockery::on(fn ($data): bool => $data instanceof OperationData && $data->status === OperationStatus::Cancelled), Mockery::any(), Mockery::any())->andReturn(true);
                });

                $function = new OperationCancelFunction($repository);
                $request = RequestObjectData::asRequest(
                    'urn:cline:forrst:ext:async:fn:cancel',
                    ['operation_id' => 'op_000000000000000000000123'],
                );
                $function->setRequest($request);
                $before = CarbonImmutable::now()->subSecond();

                // Act
                $result = $function();
                $after = CarbonImmutable::now()->addSecond();

                // Assert
                expect($result)->toHaveKey('operation_id')
                    ->and($result['operation_id'])->toBe('op_000000000000000000000123')
                    ->and($result)->toHaveKey('status')
                    ->and($result['status'])->toBe('cancelled')
                    ->and($result)->toHaveKey('cancelled_at');

                // Verify timestamp is recent (within a 2-second window)
                $cancelledAt = CarbonImmutable::parse($result['cancelled_at']);
                expect($cancelledAt->between($before, $after))->toBeTrue();
            });

            test('cancels processing operation', function (): void {
                // Arrange
                $operation = new OperationData(
                    'op_000000000000000000000456',
                    'data.export',
                    status: OperationStatus::Processing,
                    progress: 0.5,
                    startedAt: CarbonImmutable::now()->subMinutes(5),
                );

                $savedOperation = null;
                $repository = mock(OperationRepositoryInterface::class, function (MockInterface $mock) use ($operation, &$savedOperation): void {
                    $mock->shouldReceive('find')->with('op_000000000000000000000456', null)->andReturn($operation);
                    $mock->shouldReceive('saveIfVersionMatches')->once()->with(Mockery::on(function ($data) use (&$savedOperation): bool {
                        $savedOperation = $data;

                        return $data instanceof OperationData && $data->status === OperationStatus::Cancelled;
                    }), Mockery::any(), Mockery::any())->andReturn(true);
                });

                $function = new OperationCancelFunction($repository);
                $request = RequestObjectData::asRequest(
                    'urn:cline:forrst:ext:async:fn:cancel',
                    ['operation_id' => 'op_000000000000000000000456'],
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['operation_id'])->toBe('op_000000000000000000000456')
                    ->and($result['status'])->toBe('cancelled')
                    ->and($savedOperation->status)->toBe(OperationStatus::Cancelled)
                    ->and($savedOperation->cancelledAt)->not()->toBeNull();
            });

            test('updates operation status to cancelled', function (): void {
                // Arrange
                $operation = new OperationData(
                    'op_000000000000000000000789',
                    'urn:cline:forrst:fn:test:function',
                    status: OperationStatus::Pending,
                );

                $savedOperation = null;
                $repository = mock(OperationRepositoryInterface::class, function (MockInterface $mock) use ($operation, &$savedOperation): void {
                    $mock->shouldReceive('find')->with('op_000000000000000000000789', null)->andReturn($operation);
                    $mock->shouldReceive('saveIfVersionMatches')->once()->with(Mockery::on(function ($data) use (&$savedOperation): bool {
                        $savedOperation = $data;

                        return $data instanceof OperationData && $data->status === OperationStatus::Cancelled;
                    }), Mockery::any(), Mockery::any())->andReturn(true);
                });

                $function = new OperationCancelFunction($repository);
                $request = RequestObjectData::asRequest(
                    'urn:cline:forrst:ext:async:fn:cancel',
                    ['operation_id' => 'op_000000000000000000000789'],
                );
                $function->setRequest($request);

                // Act
                $function();

                // Assert
                expect($savedOperation->status)->toBe(OperationStatus::Cancelled);
            });

            test('sets cancelled_at timestamp on operation', function (): void {
                // Arrange
                $operation = new OperationData(
                    'op_000000000000000000000999',
                    'urn:cline:forrst:fn:test:function',
                    status: OperationStatus::Pending,
                );

                $savedOperation = null;
                $repository = mock(OperationRepositoryInterface::class, function (MockInterface $mock) use ($operation, &$savedOperation): void {
                    $mock->shouldReceive('find')->with('op_000000000000000000000999', null)->andReturn($operation);
                    $mock->shouldReceive('saveIfVersionMatches')->once()->with(Mockery::on(function ($data) use (&$savedOperation): bool {
                        $savedOperation = $data;

                        return $data instanceof OperationData && $data->status === OperationStatus::Cancelled;
                    }), Mockery::any(), Mockery::any())->andReturn(true);
                });

                $function = new OperationCancelFunction($repository);
                $request = RequestObjectData::asRequest(
                    'urn:cline:forrst:ext:async:fn:cancel',
                    ['operation_id' => 'op_000000000000000000000999'],
                );
                $function->setRequest($request);
                $before = CarbonImmutable::now();

                // Act
                $function();
                $after = CarbonImmutable::now();

                // Assert
                expect($savedOperation->cancelledAt)->not()->toBeNull()
                    ->and($savedOperation->cancelledAt)->toBeInstanceOf(CarbonImmutable::class)
                    ->and($savedOperation->cancelledAt->between($before, $after))->toBeTrue();
            });

            test('saves operation to repository', function (): void {
                // Arrange
                $operation = new OperationData(
                    'op_0000000000000000000save',
                    'urn:cline:forrst:fn:test:function',
                    status: OperationStatus::Pending,
                );

                $repository = mock(OperationRepositoryInterface::class, function (MockInterface $mock) use ($operation): void {
                    $mock->shouldReceive('find')->with('op_0000000000000000000save', null)->andReturn($operation);
                    $mock->shouldReceive('saveIfVersionMatches')->once()->with(Mockery::on(fn ($data): bool => $data instanceof OperationData && $data->status === OperationStatus::Cancelled), Mockery::any(), Mockery::any())->andReturn(true);
                });

                $function = new OperationCancelFunction($repository);
                $request = RequestObjectData::asRequest(
                    'urn:cline:forrst:ext:async:fn:cancel',
                    ['operation_id' => 'op_0000000000000000000save'],
                );
                $function->setRequest($request);

                // Act
                $function();

                // Assert - verified by mock expectations
                $repository->shouldHaveReceived('saveIfVersionMatches')->once();
            });
        });
    });

    describe('Edge Cases', function (): void {
        describe('terminal status checks', function (): void {
            test('operation is terminal after cancellation', function (): void {
                // Arrange
                $operation = new OperationData(
                    'op_000000000000000000000123',
                    'urn:cline:forrst:fn:test:function',
                    status: OperationStatus::Pending,
                );

                $savedOperation = null;
                $repository = mock(OperationRepositoryInterface::class, function (MockInterface $mock) use ($operation, &$savedOperation): void {
                    $mock->shouldReceive('find')->with('op_000000000000000000000123', null)->andReturn($operation);
                    $mock->shouldReceive('saveIfVersionMatches')->once()->with(Mockery::on(function ($data) use (&$savedOperation): bool {
                        $savedOperation = $data;

                        return $data instanceof OperationData && $data->status === OperationStatus::Cancelled;
                    }), Mockery::any(), Mockery::any())->andReturn(true);
                });

                $function = new OperationCancelFunction($repository);
                $request = RequestObjectData::asRequest(
                    'urn:cline:forrst:ext:async:fn:cancel',
                    ['operation_id' => 'op_000000000000000000000123'],
                );
                $function->setRequest($request);

                // Act
                $function();

                // Assert
                expect($savedOperation->isTerminal())->toBeTrue()
                    ->and($savedOperation->isCancelled())->toBeTrue();
            });
        });

        describe('timestamp precision', function (): void {
            test('cancelled_at matches return value timestamp', function (): void {
                // Arrange
                $operation = new OperationData(
                    'op_0000000000000000000time',
                    'urn:cline:forrst:fn:test:function',
                    status: OperationStatus::Pending,
                );

                $savedOperation = null;
                $repository = mock(OperationRepositoryInterface::class, function (MockInterface $mock) use ($operation, &$savedOperation): void {
                    $mock->shouldReceive('find')->with('op_0000000000000000000time', null)->andReturn($operation);
                    $mock->shouldReceive('saveIfVersionMatches')->once()->with(Mockery::on(function ($data) use (&$savedOperation): bool {
                        $savedOperation = $data;

                        return $data instanceof OperationData && $data->status === OperationStatus::Cancelled;
                    }), Mockery::any(), Mockery::any())->andReturn(true);
                });

                $function = new OperationCancelFunction($repository);
                $request = RequestObjectData::asRequest(
                    'urn:cline:forrst:ext:async:fn:cancel',
                    ['operation_id' => 'op_0000000000000000000time'],
                );
                $function->setRequest($request);

                // Act
                $result = $function();

                // Assert
                expect($result['cancelled_at'])->toBe($savedOperation->cancelledAt->toIso8601String());
            });
        });
    });

    describe('Sad Paths', function (): void {
        describe('operation not found', function (): void {
            test('throws NotFoundException when operation does not exist', function (): void {
                // Arrange
                $repository = mock(OperationRepositoryInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('find')->with('op_000000000000000000000001', null)->andReturn(null);
                });

                $function = new OperationCancelFunction($repository);
                $request = RequestObjectData::asRequest(
                    'urn:cline:forrst:ext:async:fn:cancel',
                    ['operation_id' => 'op_000000000000000000000001'],
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())->toThrow(OperationNotFoundException::class);
            });
        });

        describe('cannot cancel terminal operations', function (): void {
            test('throws OperationException when trying to cancel completed operation', function (): void {
                // Arrange
                $operation = new OperationData(
                    'op_000000000000000completed',
                    'urn:cline:forrst:fn:test:function',
                    status: OperationStatus::Completed,
                    result: ['data' => 'result'],
                    startedAt: CarbonImmutable::now()->subMinutes(5),
                    completedAt: CarbonImmutable::now(),
                );

                $repository = mock(OperationRepositoryInterface::class, function (MockInterface $mock) use ($operation): void {
                    $mock->shouldReceive('find')->with('op_000000000000000completed', null)->andReturn($operation);
                });

                $function = new OperationCancelFunction($repository);
                $request = RequestObjectData::asRequest(
                    'urn:cline:forrst:ext:async:fn:cancel',
                    ['operation_id' => 'op_000000000000000completed'],
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())->toThrow(OperationCannotCancelException::class);
            });

            test('throws OperationException when trying to cancel failed operation', function (): void {
                // Arrange
                $operation = new OperationData(
                    'op_000000000000000000failed',
                    'urn:cline:forrst:fn:test:function',
                    status: OperationStatus::Failed,
                    errors: [],
                    startedAt: CarbonImmutable::now()->subMinutes(5),
                    completedAt: CarbonImmutable::now(),
                );

                $repository = mock(OperationRepositoryInterface::class, function (MockInterface $mock) use ($operation): void {
                    $mock->shouldReceive('find')->with('op_000000000000000000failed', null)->andReturn($operation);
                });

                $function = new OperationCancelFunction($repository);
                $request = RequestObjectData::asRequest(
                    'urn:cline:forrst:ext:async:fn:cancel',
                    ['operation_id' => 'op_000000000000000000failed'],
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())->toThrow(OperationCannotCancelException::class);
            });

            test('throws OperationException when trying to cancel already cancelled operation', function (): void {
                // Arrange
                $operation = new OperationData(
                    'op_000000000000000cancelled',
                    'urn:cline:forrst:fn:test:function',
                    status: OperationStatus::Cancelled,
                    startedAt: CarbonImmutable::now()->subMinutes(5),
                    cancelledAt: CarbonImmutable::now()->subMinutes(2),
                );

                $repository = mock(OperationRepositoryInterface::class, function (MockInterface $mock) use ($operation): void {
                    $mock->shouldReceive('find')->with('op_000000000000000cancelled', null)->andReturn($operation);
                });

                $function = new OperationCancelFunction($repository);
                $request = RequestObjectData::asRequest(
                    'urn:cline:forrst:ext:async:fn:cancel',
                    ['operation_id' => 'op_000000000000000cancelled'],
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())->toThrow(OperationCannotCancelException::class);
            });
        });

        describe('repository errors', function (): void {
            test('propagates repository find exception', function (): void {
                // Arrange
                $repository = mock(OperationRepositoryInterface::class, function (MockInterface $mock): void {
                    $mock->shouldReceive('find')->andThrow(
                        new RuntimeException('Database error'),
                    );
                });

                $function = new OperationCancelFunction($repository);
                $request = RequestObjectData::asRequest(
                    'urn:cline:forrst:ext:async:fn:cancel',
                    ['operation_id' => 'op_000000000000000000000123'],
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())->toThrow(RuntimeException::class);
            });

            test('propagates repository save exception', function (): void {
                // Arrange
                $operation = new OperationData(
                    'op_000000000000000000000123',
                    'urn:cline:forrst:fn:test:function',
                    status: OperationStatus::Pending,
                );

                $repository = mock(OperationRepositoryInterface::class, function (MockInterface $mock) use ($operation): void {
                    $mock->shouldReceive('find')->with('op_000000000000000000000123', null)->andReturn($operation);
                    $mock->shouldReceive('saveIfVersionMatches')->andThrow(
                        new RuntimeException('Save failed'),
                    );
                });

                $function = new OperationCancelFunction($repository);
                $request = RequestObjectData::asRequest(
                    'urn:cline:forrst:ext:async:fn:cancel',
                    ['operation_id' => 'op_000000000000000000000123'],
                );
                $function->setRequest($request);

                // Act & Assert
                expect(fn (): array => $function())->toThrow(RuntimeException::class);
            });
        });
    });
});

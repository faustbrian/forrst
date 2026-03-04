<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Contracts\FunctionInterface;
use Cline\Forrst\Data\CallData;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Discovery\ArgumentData;
use Cline\Forrst\Discovery\ResultDescriptorData;
use Cline\Forrst\Functions\AbstractFunction;
use Cline\Forrst\Functions\Concerns\InteractsWithAuthentication;
use Cline\Forrst\Functions\Concerns\InteractsWithCancellation;
use Cline\Forrst\Functions\Concerns\InteractsWithQueryBuilder;
use Cline\Forrst\Functions\Concerns\InteractsWithTransformer;

/**
 * Concrete implementation of AbstractFunction for testing purposes.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ConcreteTestFunction extends AbstractFunction
{
    public function handle(): mixed
    {
        return ['test' => 'result'];
    }
}

/**
 * Custom function with overridden getName for testing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CustomNamedFunction extends AbstractFunction
{
    #[Override()]
    public function getUrn(): string
    {
        return 'urn:custom:forrst:fn:function:name';
    }

    public function handle(): mixed
    {
        return ['custom' => 'result'];
    }
}

/**
 * Function with overridden summary for testing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CustomSummaryFunction extends AbstractFunction
{
    #[Override()]
    public function getSummary(): string
    {
        return 'This is a custom summary for testing purposes';
    }

    public function handle(): mixed
    {
        return ['summary' => 'test'];
    }
}

/**
 * Function with custom version for testing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CustomVersionFunction extends AbstractFunction
{
    #[Override()]
    public function getVersion(): string
    {
        return '2.0.0';
    }

    public function handle(): mixed
    {
        return ['version' => 'test'];
    }
}

/**
 * Function with custom arguments for testing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FunctionWithArguments extends AbstractFunction
{
    #[Override()]
    public function getArguments(): array
    {
        return [
            ArgumentData::from([
                'name' => 'userId',
                'schema' => ['type' => 'integer'],
            ]),
            ArgumentData::from([
                'name' => 'email',
                'schema' => ['type' => 'string'],
            ]),
        ];
    }

    public function handle(): mixed
    {
        return ['arguments' => 'test'];
    }
}

/**
 * Function with custom result descriptor for testing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FunctionWithResult extends AbstractFunction
{
    #[Override()]
    public function getResult(): ResultDescriptorData
    {
        return ResultDescriptorData::from([
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                ],
            ],
            'description' => 'User data object',
        ]);
    }

    public function handle(): mixed
    {
        return ['result' => 'test'];
    }
}

/**
 * Function with custom errors for testing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FunctionWithErrors extends AbstractFunction
{
    #[Override()]
    public function getErrors(): array
    {
        return [
            ['code' => 'USER_NOT_FOUND', 'message' => 'User not found'],
            ['code' => 'INVALID_PERMISSIONS', 'message' => 'Invalid permissions'],
        ];
    }

    public function handle(): mixed
    {
        return ['errors' => 'test'];
    }
}

/**
 * Function with multi-word class name for snake_case testing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class UserProfileSettings extends AbstractFunction
{
    public function handle(): mixed
    {
        return ['profile' => 'settings'];
    }
}

/**
 * Function with single word for snake_case testing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Dashboard extends AbstractFunction
{
    public function handle(): mixed
    {
        return ['dashboard' => 'data'];
    }
}

describe('AbstractFunction', function (): void {
    describe('Happy Paths', function (): void {
        describe('getName()', function (): void {
            test('generates function name from class name in snake_case format', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act
                $result = $function->getUrn();

                // Assert
                expect($result)->toBe('urn:app:forrst:fn:concrete-test');
            });

            test('converts multi-word class names to snake_case correctly', function (): void {
                // Arrange
                $function = new UserProfileSettings();

                // Act
                $result = $function->getUrn();

                // Assert
                expect($result)->toBe('urn:app:forrst:fn:user-profile-settings');
            });

            test('handles single-word class names correctly', function (): void {
                // Arrange
                $function = new Dashboard();

                // Act
                $result = $function->getUrn();

                // Assert
                expect($result)->toBe('urn:app:forrst:fn:dashboard');
            });

            test('can be overridden to provide custom function names', function (): void {
                // Arrange
                $function = new CustomNamedFunction();

                // Act
                $result = $function->getUrn();

                // Assert
                expect($result)->toBe('urn:custom:forrst:fn:function:name');
            });

            test('always prefixes auto-generated names with app namespace', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act
                $result = $function->getUrn();

                // Assert
                expect($result)->toStartWith('urn:app:forrst:fn:');
            });
        });

        describe('getVersion()', function (): void {
            test('returns default version 1', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act
                $result = $function->getVersion();

                // Assert
                expect($result)->toBe('1.0.0');
            });

            test('can be overridden to provide custom version', function (): void {
                // Arrange
                $function = new CustomVersionFunction();

                // Act
                $result = $function->getVersion();

                // Assert
                expect($result)->toBe('2.0.0');
            });
        });

        describe('getSummary()', function (): void {
            test('returns function name by default', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act
                $result = $function->getSummary();

                // Assert
                expect($result)->toBe('urn:app:forrst:fn:concrete-test')
                    ->and($result)->toBe($function->getUrn());
            });

            test('can be overridden to provide custom summaries', function (): void {
                // Arrange
                $function = new CustomSummaryFunction();

                // Act
                $result = $function->getSummary();

                // Assert
                expect($result)->toBe('This is a custom summary for testing purposes')
                    ->and($result)->not()->toBe($function->getUrn());
            });

            test('returns string type summary', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act
                $result = $function->getSummary();

                // Assert
                expect($result)->toBeString();
            });
        });

        describe('getArguments()', function (): void {
            test('returns empty array by default', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toBeEmpty();
            });

            test('can be overridden to define function arguments', function (): void {
                // Arrange
                $function = new FunctionWithArguments();

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toHaveCount(2)
                    ->and($result[0])->toBeInstanceOf(ArgumentData::class)
                    ->and($result[1])->toBeInstanceOf(ArgumentData::class);
            });

            test('returns ArgumentData instances for Forrst Discovery compatibility', function (): void {
                // Arrange
                $function = new FunctionWithArguments();

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result[0]->name)->toBe('userId')
                    ->and($result[0]->schema)->toBe(['type' => 'integer'])
                    ->and($result[1]->name)->toBe('email')
                    ->and($result[1]->schema)->toBe(['type' => 'string']);
            });
        });

        describe('getResult()', function (): void {
            test('returns null by default', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act
                $result = $function->getResult();

                // Assert
                expect($result)->toBeNull();
            });

            test('can be overridden to define result descriptor', function (): void {
                // Arrange
                $function = new FunctionWithResult();

                // Act
                $result = $function->getResult();

                // Assert
                expect($result)->toBeInstanceOf(ResultDescriptorData::class)
                    ->and($result)->not()->toBeNull();
            });

            test('returns ResultDescriptorData with schema definition', function (): void {
                // Arrange
                $function = new FunctionWithResult();

                // Act
                $result = $function->getResult();

                // Assert
                expect($result->schema)->toBeArray()
                    ->and($result->schema)->toHaveKey('type')
                    ->and($result->schema['type'])->toBe('object')
                    ->and($result->schema)->toHaveKey('properties')
                    ->and($result->description)->toBe('User data object');
            });
        });

        describe('getErrors()', function (): void {
            test('returns empty array by default', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act
                $result = $function->getErrors();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toBeEmpty();
            });

            test('can be overridden to define error descriptors', function (): void {
                // Arrange
                $function = new FunctionWithErrors();

                // Act
                $result = $function->getErrors();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toHaveCount(2);
            });

            test('returns error arrays with string-based codes for Forrst compatibility', function (): void {
                // Arrange
                $function = new FunctionWithErrors();

                // Act
                $result = $function->getErrors();

                // Assert
                expect($result[0])->toBeArray()
                    ->and($result[0]['code'])->toBe('USER_NOT_FOUND')
                    ->and($result[0]['message'])->toBe('User not found')
                    ->and($result[1])->toBeArray()
                    ->and($result[1]['code'])->toBe('INVALID_PERMISSIONS')
                    ->and($result[1]['message'])->toBe('Invalid permissions');
            });
        });

        describe('setRequest()', function (): void {
            test('stores request object for later access', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();
                $requestObject = RequestObjectData::from([
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => '123',
                    'call' => CallData::from([
                        'function' => 'urn:cline:forrst:fn:test:function',
                        'arguments' => ['key' => 'value'],
                    ]),
                ]);

                // Act
                $function->setRequest($requestObject);

                // Assert
                $reflection = new ReflectionClass($function);
                $property = $reflection->getProperty('requestObject');

                $storedRequest = $property->getValue($function);

                expect($storedRequest)->toBe($requestObject)
                    ->and($storedRequest->call->function)->toBe('urn:cline:forrst:fn:test:function')
                    ->and($storedRequest->call->arguments)->toBe(['key' => 'value']);
            });

            test('accepts request with null arguments', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();
                $requestObject = RequestObjectData::from([
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => 'abc-123',
                    'call' => CallData::from([
                        'function' => 'test.noArgs',
                        'arguments' => null,
                    ]),
                ]);

                // Act
                $function->setRequest($requestObject);

                // Assert
                $reflection = new ReflectionClass($function);
                $property = $reflection->getProperty('requestObject');

                $storedRequest = $property->getValue($function);

                expect($storedRequest->call->arguments)->toBeNull();
            });

            test('accepts request with nested arguments structure', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();
                $requestObject = RequestObjectData::from([
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => '456',
                    'call' => CallData::from([
                        'function' => 'test.nested',
                        'arguments' => [
                            'user' => [
                                'name' => 'John Doe',
                                'email' => 'john@example.com',
                                'settings' => [
                                    'theme' => 'dark',
                                ],
                            ],
                        ],
                    ]),
                ]);

                // Act
                $function->setRequest($requestObject);

                // Assert
                $reflection = new ReflectionClass($function);
                $property = $reflection->getProperty('requestObject');

                $storedRequest = $property->getValue($function);

                expect($storedRequest->call->arguments)->toHaveKey('user')
                    ->and($storedRequest->call->arguments['user'])->toHaveKey('settings');
            });
        });

        describe('FunctionInterface implementation', function (): void {
            test('implements FunctionInterface contract', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act & Assert
                expect($function)->toBeInstanceOf(FunctionInterface::class);
            });

            test('provides all required FunctionInterface methods', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act & Assert
                expect(method_exists($function, 'getUrn'))->toBeTrue()
                    ->and(method_exists($function, 'getVersion'))->toBeTrue()
                    ->and(method_exists($function, 'getSummary'))->toBeTrue()
                    ->and(method_exists($function, 'getArguments'))->toBeTrue()
                    ->and(method_exists($function, 'getResult'))->toBeTrue()
                    ->and(method_exists($function, 'getErrors'))->toBeTrue()
                    ->and(method_exists($function, 'setRequest'))->toBeTrue()
                    ->and(method_exists($function, 'handle'))->toBeTrue();
            });
        });
    });

    describe('Sad Paths', function (): void {
        describe('getName() error handling', function (): void {
            test('handles empty class name suffix gracefully', function (): void {
                // Arrange - even with unusual naming, should still work
                $function = new ConcreteTestFunction();

                // Act
                $result = $function->getUrn();

                // Assert - should not throw exception
                expect($result)->toBeString()
                    ->and($result)->not()->toBeEmpty();
            });
        });

        describe('setRequest() validation', function (): void {
            test('allows overwriting previous request object', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();
                $firstRequest = RequestObjectData::from([
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => '1',
                    'call' => CallData::from([
                        'function' => 'first.function',
                    ]),
                ]);
                $secondRequest = RequestObjectData::from([
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => '2',
                    'call' => CallData::from([
                        'function' => 'second.function',
                    ]),
                ]);

                // Act
                $function->setRequest($firstRequest);
                $function->setRequest($secondRequest); // Overwrite

                // Assert
                $reflection = new ReflectionClass($function);
                $property = $reflection->getProperty('requestObject');

                $storedRequest = $property->getValue($function);

                expect($storedRequest)->toBe($secondRequest)
                    ->and($storedRequest->call->function)->toBe('second.function');
            });
        });
    });

    describe('Edge Cases', function (): void {
        describe('getName() with special characters', function (): void {
            test('handles uppercase acronyms in class names', function (): void {
                // Arrange
                /**
                 * @author Brian Faust <brian@cline.sh>
                 */
                final class JSONAPIFunction extends AbstractFunction
                {
                    public function handle(): mixed
                    {
                        return [];
                    }
                }

                $function = new JSONAPIFunction();

                // Act
                $result = $function->getUrn();

                // Assert
                expect($result)->toBe('urn:app:forrst:fn:j-s-o-n-a-p-i')
                    ->and($result)->toContain('-');
            });

            test('handles consecutive uppercase letters', function (): void {
                // Arrange
                /**
                 * @author Brian Faust <brian@cline.sh>
                 */
                final class HTTPSConnection extends AbstractFunction
                {
                    public function handle(): mixed
                    {
                        return [];
                    }
                }

                $function = new HTTPSConnection();

                // Act
                $result = $function->getUrn();

                // Assert
                expect($result)->toBeString()
                    ->and($result)->toStartWith('urn:app:forrst:fn:');
            });
        });

        describe('getArguments() edge cases', function (): void {
            test('handles empty arguments array definition', function (): void {
                // Arrange
                /**
                 * @author Brian Faust <brian@cline.sh>
                 */
                final class EmptyArgumentsFunction extends AbstractFunction
                {
                    #[Override()]
                    public function getArguments(): array
                    {
                        return [];
                    }

                    public function handle(): mixed
                    {
                        return [];
                    }
                }

                $function = new EmptyArgumentsFunction();

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toBeEmpty();
            });

            test('handles large number of arguments', function (): void {
                // Arrange
                /**
                 * @author Brian Faust <brian@cline.sh>
                 */
                final class ManyArgumentsFunction extends AbstractFunction
                {
                    #[Override()]
                    public function getArguments(): array
                    {
                        $args = [];

                        for ($i = 1; $i <= 20; ++$i) {
                            $args[] = ArgumentData::from([
                                'name' => 'arg'.$i,
                                'schema' => ['type' => 'string'],
                            ]);
                        }

                        return $args;
                    }

                    public function handle(): mixed
                    {
                        return [];
                    }
                }

                $function = new ManyArgumentsFunction();

                // Act
                $result = $function->getArguments();

                // Assert
                expect($result)->toHaveCount(20)
                    ->and($result[0]->name)->toBe('arg1')
                    ->and($result[19]->name)->toBe('arg20');
            });
        });

        describe('getErrors() edge cases', function (): void {
            test('handles single error definition', function (): void {
                // Arrange
                /**
                 * @author Brian Faust <brian@cline.sh>
                 */
                final class SingleErrorFunction extends AbstractFunction
                {
                    #[Override()]
                    public function getErrors(): array
                    {
                        return [
                            ['code' => 'SERVER_ERROR', 'message' => 'Server error'],
                        ];
                    }

                    public function handle(): mixed
                    {
                        return [];
                    }
                }

                $function = new SingleErrorFunction();

                // Act
                $result = $function->getErrors();

                // Assert
                expect($result)->toHaveCount(1)
                    ->and($result[0]['code'])->toBe('SERVER_ERROR');
            });

            test('handles many error definitions', function (): void {
                // Arrange
                /**
                 * @author Brian Faust <brian@cline.sh>
                 */
                final class ManyErrorsFunction extends AbstractFunction
                {
                    #[Override()]
                    public function getErrors(): array
                    {
                        $errors = [];

                        for ($i = 1; $i <= 10; ++$i) {
                            $errors[] = ['code' => 'ERROR_'.$i, 'message' => 'Error '.$i];
                        }

                        return $errors;
                    }

                    public function handle(): mixed
                    {
                        return [];
                    }
                }

                $function = new ManyErrorsFunction();

                // Act
                $result = $function->getErrors();

                // Assert
                expect($result)->toHaveCount(10)
                    ->and($result[0]['code'])->toBe('ERROR_1')
                    ->and($result[9]['code'])->toBe('ERROR_10');
            });
        });

        describe('setRequest() with various request types', function (): void {
            test('handles request with standard id', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();
                $requestObject = RequestObjectData::from([
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => 'notification-123',
                    'call' => CallData::from([
                        'function' => 'test.notification',
                    ]),
                ]);

                // Act
                $function->setRequest($requestObject);

                // Assert
                $reflection = new ReflectionClass($function);
                $property = $reflection->getProperty('requestObject');

                $storedRequest = $property->getValue($function);

                expect($storedRequest->id)->toBe('notification-123');
            });

            test('handles request with string id', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();
                $requestObject = RequestObjectData::from([
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => 'string-id-123',
                    'call' => CallData::from([
                        'function' => 'urn:cline:forrst:fn:test:function',
                    ]),
                ]);

                // Act
                $function->setRequest($requestObject);

                // Assert
                $reflection = new ReflectionClass($function);
                $property = $reflection->getProperty('requestObject');

                $storedRequest = $property->getValue($function);

                expect($storedRequest->id)->toBe('string-id-123')
                    ->and($storedRequest->id)->toBeString();
            });

            test('handles request with numeric-like string id', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();
                $requestObject = RequestObjectData::from([
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => '42',
                    'call' => CallData::from([
                        'function' => 'urn:cline:forrst:fn:test:function',
                    ]),
                ]);

                // Act
                $function->setRequest($requestObject);

                // Assert
                $reflection = new ReflectionClass($function);
                $property = $reflection->getProperty('requestObject');

                $storedRequest = $property->getValue($function);

                expect($storedRequest->id)->toBe('42')
                    ->and($storedRequest->id)->toBeString();
            });

            test('handles request with complex arguments array', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();
                $complexArguments = [
                    'filter' => [
                        'status' => ['active', 'pending'],
                        'created_at' => ['gte' => '2024-01-01'],
                    ],
                    'sort' => ['-created_at', 'name'],
                    'include' => ['author', 'comments'],
                    'fields' => [
                        'users' => ['id', 'name', 'email'],
                        'posts' => ['id', 'title'],
                    ],
                ];
                $requestObject = RequestObjectData::from([
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => 'complex-1',
                    'call' => CallData::from([
                        'function' => 'test.complex',
                        'arguments' => $complexArguments,
                    ]),
                ]);

                // Act
                $function->setRequest($requestObject);

                // Assert
                $reflection = new ReflectionClass($function);
                $property = $reflection->getProperty('requestObject');

                $storedRequest = $property->getValue($function);

                expect($storedRequest->call->arguments)->toBe($complexArguments)
                    ->and($storedRequest->getArgument('filter.status'))->toBe(['active', 'pending'])
                    ->and($storedRequest->getArgument('sort'))->toBe(['-created_at', 'name']);
            });
        });

        describe('method chaining scenarios', function (): void {
            test('allows setting request before calling getName', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();
                $requestObject = RequestObjectData::from([
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => '1',
                    'call' => CallData::from([
                        'function' => 'urn:cline:forrst:fn:test:function',
                    ]),
                ]);

                // Act
                $function->setRequest($requestObject);
                $name = $function->getUrn();

                // Assert
                expect($name)->toBe('urn:app:forrst:fn:concrete-test');
            });

            test('getName remains consistent after setRequest', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();
                $nameBefore = $function->getUrn();

                $requestObject = RequestObjectData::from([
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => '1',
                    'call' => CallData::from([
                        'function' => 'different.function',
                    ]),
                ]);
                $function->setRequest($requestObject);

                // Act
                $nameAfter = $function->getUrn();

                // Assert
                expect($nameAfter)->toBe($nameBefore)
                    ->and($nameAfter)->toBe('urn:app:forrst:fn:concrete-test');
            });
        });
    });

    describe('Trait Integration', function (): void {
        describe('InteractsWithAuthentication trait', function (): void {
            test('includes InteractsWithAuthentication trait', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act
                $traits = class_uses_recursive($function);

                // Assert
                expect($traits)->toContain(InteractsWithAuthentication::class);
            });

            test('provides getCurrentUser method from trait', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act & Assert
                expect(method_exists($function, 'getCurrentUser'))->toBeTrue();
            });
        });

        describe('InteractsWithQueryBuilder trait', function (): void {
            test('includes InteractsWithQueryBuilder trait', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act
                $traits = class_uses_recursive($function);

                // Assert
                expect($traits)->toContain(InteractsWithQueryBuilder::class);
            });

            test('provides query method from trait', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act & Assert
                expect(method_exists($function, 'query'))->toBeTrue();
            });
        });

        describe('InteractsWithTransformer trait', function (): void {
            test('includes InteractsWithTransformer trait', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act
                $traits = class_uses_recursive($function);

                // Assert
                expect($traits)->toContain(InteractsWithTransformer::class);
            });

            test('provides item method from trait', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act & Assert
                expect(method_exists($function, 'item'))->toBeTrue();
            });

            test('provides collection method from trait', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act & Assert
                expect(method_exists($function, 'collection'))->toBeTrue();
            });

            test('provides cursorPaginate method from trait', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act & Assert
                expect(method_exists($function, 'cursorPaginate'))->toBeTrue();
            });

            test('provides paginate method from trait', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act & Assert
                expect(method_exists($function, 'paginate'))->toBeTrue();
            });

            test('provides simplePaginate method from trait', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act & Assert
                expect(method_exists($function, 'simplePaginate'))->toBeTrue();
            });
        });

        describe('all three traits work together', function (): void {
            test('function has access to all trait methods', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();
                $expectedMethods = [
                    // From InteractsWithAuthentication
                    'getCurrentUser',
                    // From InteractsWithQueryBuilder
                    'query',
                    // From InteractsWithTransformer
                    'item',
                    'collection',
                    'cursorPaginate',
                    'paginate',
                    'simplePaginate',
                ];

                // Act & Assert
                foreach ($expectedMethods as $methodName) {
                    expect(method_exists($function, $methodName))->toBeTrue();
                }
            });

            test('all three traits are present in class hierarchy', function (): void {
                // Arrange
                $function = new ConcreteTestFunction();

                // Act
                $traits = class_uses_recursive($function);

                // Assert
                expect($traits)->toHaveCount(4)
                    ->and($traits)->toContain(InteractsWithAuthentication::class)
                    ->and($traits)->toContain(InteractsWithCancellation::class)
                    ->and($traits)->toContain(InteractsWithQueryBuilder::class)
                    ->and($traits)->toContain(InteractsWithTransformer::class);
            });
        });
    });
});

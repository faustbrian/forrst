<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Contracts\UnwrappedResponseInterface;
use Cline\Forrst\Data\CallData;
use Cline\Forrst\Data\ErrorData;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Exceptions\InvalidDataException;
use Cline\Forrst\Functions\AbstractFunction;
use Cline\Forrst\Jobs\CallFunction;
use Illuminate\Validation\ValidationException;
use Tests\Support\Exceptions\SimulatedException;
use Tests\Support\Exceptions\SimulatedLogicException;
use Tests\Support\Exceptions\SimulatedRuntimeException;
use Tests\Support\Fixtures\ProductData;
use Tests\Support\Fixtures\ValidatedUserData;

describe('CallFunction', function (): void {
    describe('Happy Paths', function (): void {
        test('executes method successfully and returns wrapped response', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => '123',
                'call' => CallData::from([
                    'function' => 'test.method',
                    'arguments' => ['name' => 'John'],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(string $name): array
                {
                    return ['status' => 'success', 'data' => ['name' => $name]];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->protocol)->toBeInstanceOf(ProtocolData::class)
                ->and($response->id)->toBe('123')
                ->and($response->result)->toBe(['status' => 'success', 'data' => ['name' => 'John']])
                ->and($response->getFirstError())->toBeNull();
        });

        test('executes method with no parameters and returns result', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'abc-123',
                'call' => CallData::from([
                    'function' => 'test.noParams',
                    'arguments' => null,
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(): array
                {
                    return ['message' => 'No params required'];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->result)->toBe(['message' => 'No params required']);
        });

        test('returns unwrapped response when method implements UnwrappedResponseInterface', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => '456',
                'call' => CallData::from([
                    'function' => 'test.unwrapped',
                    'arguments' => [],
                ]),
            ]);

            $method = new class() extends AbstractFunction implements UnwrappedResponseInterface
            {
                public function handle(): array
                {
                    return ['custom' => 'structure', 'items' => [1, 2, 3]];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response)->toBeArray()
                ->and($response)->toBe(['custom' => 'structure', 'items' => [1, 2, 3]])
                ->and($response)->not->toBeInstanceOf(ResponseData::class);
        });

        test('resolves method parameters from request data', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => '789',
                'call' => CallData::from([
                    'function' => 'user.create',
                    'arguments' => [
                        'name' => 'Jane Doe',
                        'email' => 'jane@example.com',
                        'age' => 30,
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(string $name, string $email, int $age): array
                {
                    return ['name' => $name, 'email' => $email, 'age' => $age];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->result)->toBe([
                    'name' => 'Jane Doe',
                    'email' => 'jane@example.com',
                    'age' => 30,
                ]);
        });

        test('handles method with requestObject parameter correctly', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'req-123',
                'call' => CallData::from([
                    'function' => 'test.withRequestObject',
                    'arguments' => ['value' => 42],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(RequestObjectData $requestObject, int $value): array
                {
                    return [
                        'method' => $requestObject->getFunction(),
                        'value' => $value,
                    ];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response->result)->toBe([
                'method' => 'test.withRequestObject',
                'value' => 42,
            ]);
        });

        test('resolves snake_case parameters to camelCase method parameters', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'snake-123',
                'call' => CallData::from([
                    'function' => 'test.snakeCase',
                    'arguments' => [
                        'first.name' => 'John',
                        'last.name' => 'Smith',
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(string $firstName, string $lastName): array
                {
                    return ['full_name' => $firstName.' '.$lastName];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response->result)->toBe(['full_name' => 'John Smith']);
        });

        test('handles array data parameter when method expects array type', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'array-123',
                'call' => CallData::from([
                    'function' => 'test.arrayData',
                    'arguments' => [
                        'items' => ['a', 'b', 'c'],
                        'count' => 3,
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(array $data): array
                {
                    return ['received' => $data];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response->result['received'])->toBe([
                'items' => ['a', 'b', 'c'],
                'count' => 3,
            ]);
        });

        test('resolves Data object parameter with validateAndCreate', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'data-object-123',
                'call' => CallData::from([
                    'function' => 'user.createWithData',
                    'arguments' => [
                        'userInfo' => [
                            'name' => 'John Doe',
                            'email' => 'john@example.com',
                            'age' => 30,
                        ],
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(ValidatedUserData $userInfo): array
                {
                    return [
                        'created' => true,
                        'user' => $userInfo->toArray(),
                    ];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->result)->toBe([
                    'created' => true,
                    'user' => [
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                        'age' => 30,
                    ],
                ]);
        });

        test('resolves Data object when parameter name is "data"', function (): void {
            // Arrange - Special case where parameter is named "data"
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'data-param-123',
                'call' => CallData::from([
                    'function' => 'product.create',
                    'arguments' => [
                        'title' => 'Test Product',
                        'price' => 99.99,
                        'description' => 'A test product',
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(ProductData $data): array
                {
                    return [
                        'success' => true,
                        'product' => $data->toArray(),
                    ];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->result)->toBe([
                    'success' => true,
                    'product' => [
                        'title' => 'Test Product',
                        'price' => 99.99,
                        'description' => 'A test product',
                    ],
                ]);
        });

        test('resolves multiple Data object parameters', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'multi-data-123',
                'call' => CallData::from([
                    'function' => 'order.create',
                    'arguments' => [
                        'user' => [
                            'name' => 'Jane Smith',
                            'email' => 'jane@example.com',
                            'age' => 25,
                        ],
                        'product' => [
                            'title' => 'Premium Widget',
                            'price' => 149.99,
                        ],
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(ValidatedUserData $user, ProductData $product): array
                {
                    return [
                        'order_created' => true,
                        'user_name' => $user->name,
                        'product_title' => $product->title,
                        'total' => $product->price,
                    ];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->result)->toBe([
                    'order_created' => true,
                    'user_name' => 'Jane Smith',
                    'product_title' => 'Premium Widget',
                    'total' => 149.99,
                ]);
        });

        test('handles mixed Data objects and primitive parameters', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'mixed-params-123',
                'call' => CallData::from([
                    'function' => 'invoice.create',
                    'arguments' => [
                        'product' => [
                            'title' => 'Service Fee',
                            'price' => 200.00,
                        ],
                        'quantity' => 3,
                        'discount' => 0.1,
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(ProductData $product, int $quantity, float $discount): array
                {
                    $subtotal = $product->price * $quantity;
                    $total = $subtotal * (1 - $discount);

                    return [
                        'product' => $product->title,
                        'quantity' => $quantity,
                        'subtotal' => $subtotal,
                        'discount' => $discount * 100 .'%',
                        'total' => $total,
                    ];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->result)->toBe([
                    'product' => 'Service Fee',
                    'quantity' => 3,
                    'subtotal' => 600.00,
                    'discount' => '10%',
                    'total' => 540.00,
                ]);
        });
    });

    describe('Sad Paths', function (): void {
        test('catches exception and returns error response', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'error-123',
                'call' => CallData::from([
                    'function' => 'test.failing',
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(): never
                {
                    throw SimulatedException::somethingWentWrong();
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->protocol)->toBeInstanceOf(ProtocolData::class)
                ->and($response->id)->toBe('error-123')
                ->and($response->result)->toBeNull()
                ->and($response->getFirstError())->toBeInstanceOf(ErrorData::class);
        });

        test('handles ValidationException during Data object validation', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'validation-error',
                'call' => CallData::from([
                    'function' => 'test.validation',
                    'arguments' => [
                        'email' => 'not-an-email',
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(): never
                {
                    $validator = validator(['email' => 'not-an-email'], ['email' => 'required|email']);

                    throw new ValidationException($validator);
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->getFirstError())->toBeInstanceOf(ErrorData::class);
        });

        test('returns error response for method that throws exception', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'runtime-error',
                'call' => CallData::from([
                    'function' => 'test.runtimeError',
                    'arguments' => [],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(): array
                {
                    throw SimulatedRuntimeException::runtimeError();
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->getFirstError())->toBeInstanceOf(ErrorData::class)
                ->and($response->result)->toBeNull();
        });

        test('handles InvalidDataException during parameter resolution', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'invalid-data',
                'call' => CallData::from([
                    'function' => 'test.invalidData',
                    'arguments' => [
                        'invalid' => 'data',
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(): never
                {
                    $validator = validator(['field' => 'invalid'], ['field' => 'required|numeric']);
                    $validationException = new ValidationException($validator);

                    throw InvalidDataException::create($validationException);
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->getFirstError())->toBeInstanceOf(ErrorData::class);
        });

        test('throws InvalidDataException when Data object validation fails', function (): void {
            // Arrange - Invalid email format to trigger validation error
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'data-validation-fail',
                'call' => CallData::from([
                    'function' => 'user.create',
                    'arguments' => [
                        'user' => [
                            'name' => 'Jo',  // Too short (min 3)
                            'email' => 'not-an-email',  // Invalid email format
                            'age' => 200,  // Too high (max 150)
                        ],
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(ValidatedUserData $user): array
                {
                    return ['user' => $user->toArray()];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert - Should get error response due to validation failure
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->getFirstError())->toBeInstanceOf(ErrorData::class)
                ->and($response->getFirstError()->code)->toBe(ErrorCode::InvalidArguments->value)
                ->and($response->getFirstError()->message)->toBe('Invalid arguments')
                ->and($response->result)->toBeNull();
        });

        test('handles validation error for Data parameter named "data"', function (): void {
            // Arrange - Missing required field to trigger validation
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'data-param-validation-fail',
                'call' => CallData::from([
                    'function' => 'product.create',
                    'arguments' => [
                        'title' => 'Test',  // Valid
                        // Missing required 'price' field
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(ProductData $data): array
                {
                    return ['product' => $data->toArray()];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert - Should get error response due to missing required field
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->getFirstError())->toBeInstanceOf(ErrorData::class)
                ->and($response->getFirstError()->code)->toBe(ErrorCode::InvalidArguments->value)
                ->and($response->result)->toBeNull();
        });

        test('handles validation failure for multiple Data parameters', function (): void {
            // Arrange - Invalid data for both parameters
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'multi-data-validation-fail',
                'call' => CallData::from([
                    'function' => 'order.create',
                    'arguments' => [
                        'user' => [
                            'name' => 'Valid Name',
                            'email' => 'invalid-email',  // Invalid email
                        ],
                        'product' => [
                            'title' => 'Product',
                            'price' => -10,  // Negative price (min 0)
                        ],
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(ValidatedUserData $user, ProductData $product): array
                {
                    return [
                        'user' => $user->name,
                        'product' => $product->title,
                    ];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert - First Data validation failure should trigger error
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->getFirstError())->toBeInstanceOf(ErrorData::class)
                ->and($response->getFirstError()->code)->toBe(ErrorCode::InvalidArguments->value);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty params array', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'empty-params',
                'call' => CallData::from([
                    'function' => 'test.noParams',
                    'arguments' => [],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(): array
                {
                    return ['status' => 'ok'];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->result)->toBe(['status' => 'ok']);
        });

        test('handles null parameter values', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'null-params',
                'call' => CallData::from([
                    'function' => 'test.nullValues',
                    'arguments' => [
                        'name' => 'Test',
                        'description' => null,
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(string $name, ?string $description = null): array
                {
                    return ['name' => $name, 'description' => $description];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response->result)->toBe(['name' => 'Test', 'description' => null]);
        });

        test('filters out null values from resolved parameters', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'filter-nulls',
                'call' => CallData::from([
                    'function' => 'test.filterNulls',
                    'arguments' => [
                        'name' => 'John',
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(string $name, ?string $optional = null): array
                {
                    return ['name' => $name, 'has_optional' => $optional !== null];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response->result)->toBe(['name' => 'John', 'has_optional' => false]);
        });

        test('handles method with multiple parameter types', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'mixed-types',
                'call' => CallData::from([
                    'function' => 'test.mixedTypes',
                    'arguments' => [
                        'string' => 'text',
                        'number' => 42,
                        'float' => 3.14,
                        'bool' => true,
                        'array' => [1, 2, 3],
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(
                    string $string,
                    int $number,
                    float $float,
                    bool $bool,
                    array $array,
                ): array {
                    return ['string' => $string, 'number' => $number, 'float' => $float, 'bool' => $bool, 'array' => $array];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response->result)->toBe([
                'string' => 'text',
                'number' => 42,
                'float' => 3.14,
                'bool' => true,
                'array' => [1, 2, 3],
            ]);
        });

        test('handles request with event parameter', function (): void {
            // Arrange - Note: In Forrst, ID is always required (no notifications)
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'event-req',
                'call' => CallData::from([
                    'function' => 'test.notification',
                    'arguments' => ['event' => 'test'],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(string $event): array
                {
                    return ['received' => $event];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->id)->toBe('event-req')
                ->and($response->result)->toBe(['received' => 'test']);
        });

        test('handles nested parameter paths with dot notation', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'nested-params',
                'call' => CallData::from([
                    'function' => 'test.nested',
                    'arguments' => [
                        'user.profile.name' => 'John',
                        'user.profile.age' => 30,
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(string $userProfileName, int $userProfileAge): array
                {
                    return ['name' => $userProfileName, 'age' => $userProfileAge];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response->result)->toBe(['name' => 'John', 'age' => 30]);
        });

        test('handles exception in method that implements UnwrappedResponseInterface', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'unwrapped-error',
                'call' => CallData::from([
                    'function' => 'test.unwrappedError',
                ]),
            ]);

            $method = new class() extends AbstractFunction implements UnwrappedResponseInterface
            {
                public function handle(): never
                {
                    throw SimulatedException::unwrappedMethodFailed();
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert - Even unwrapped methods return ResponseData on error
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->getFirstError())->toBeInstanceOf(ErrorData::class);
        });

        test('preserves protocol from request in response', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'version-test',
                'call' => CallData::from([
                    'function' => 'test.version',
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(): array
                {
                    return ['version' => '1.0'];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response->protocol)->toBeInstanceOf(ProtocolData::class);
        });

        test('handles method with no type hints on parameters', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'no-types',
                'call' => CallData::from([
                    'function' => 'test.noTypes',
                    'arguments' => [
                        'value' => 'anything',
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle($value): array
                {
                    return ['value' => $value];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response->result)->toBe(['value' => 'anything']);
        });

        test('resolves parameters when reflection returns no named type', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'union-type',
                'call' => CallData::from([
                    'function' => 'test.unionType',
                    'arguments' => [
                        'value' => 'test',
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(string|int $value): array
                {
                    return ['value' => $value, 'type' => gettype($value)];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response->result)->toBe(['value' => 'test', 'type' => 'string']);
        });

        test('handles optional parameters that are not provided', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'optional-params',
                'call' => CallData::from([
                    'function' => 'test.optionalParams',
                    'arguments' => [
                        'required' => 'value',
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(string $required, ?string $optional = null, int $count = 0): array
                {
                    return [
                        'required' => $required,
                        'optional' => $optional,
                        'count' => $count,
                    ];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response->result)->toBe([
                'required' => 'value',
                'optional' => null,
                'count' => 0,
            ]);
        });

        test('preserves explicitly provided falsy values', function (): void {
            // Arrange - Explicitly provided falsy values (0, '', false) should be preserved
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'falsy-params',
                'call' => CallData::from([
                    'function' => 'test.falsyParams',
                    'arguments' => [
                        'enabled' => false,
                        'active' => true,
                        'count' => 0,
                        'name' => '',
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(bool $enabled = false, bool $active = false, ?int $count = null, ?string $name = null): array
                {
                    return [
                        'enabled' => $enabled,
                        'active' => $active,
                        'count' => $count,
                        'name' => $name,
                    ];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert - Explicitly provided falsy values must be preserved (0 and '' are valid values)
            expect($response->result)->toBe([
                'enabled' => false,
                'active' => true,
                'count' => 0,
                'name' => '',
            ]);
        });

        test('handles Data object parameter with empty object', function (): void {
            // Arrange - Empty object for Data parameter to test validation
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'empty-data-object',
                'call' => CallData::from([
                    'function' => 'process.withData',
                    'arguments' => [
                        'action' => 'validate',
                        'product' => [],  // Empty object for ProductData
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(string $action, ProductData $product): array
                {
                    return [
                        'action' => $action,
                        'product' => $product->toArray(),
                    ];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert - Should get validation error for missing required fields
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->getFirstError())->toBeInstanceOf(ErrorData::class)
                ->and($response->getFirstError()->code)->toBe(ErrorCode::InvalidArguments->value)
                ->and($response->result)->toBeNull();
        });

        test('handles Data object with partial valid data', function (): void {
            // Arrange - Some valid, some invalid fields
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'partial-data',
                'call' => CallData::from([
                    'function' => 'user.update',
                    'arguments' => [
                        'user' => [
                            'name' => 'Valid Name',
                            'email' => 'valid@example.com',
                            // age is optional and not provided
                        ],
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(ValidatedUserData $user): array
                {
                    return [
                        'updated' => true,
                        'name' => $user->name,
                        'email' => $user->email,
                        'has_age' => $user->age !== null,
                    ];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->result)->toBe([
                    'updated' => true,
                    'name' => 'Valid Name',
                    'email' => 'valid@example.com',
                    'has_age' => false,
                ]);
        });

        test('handles Data object parameter with snake_case to camelCase conversion', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'snake-case-data',
                'call' => CallData::from([
                    'function' => 'user.process',
                    'arguments' => [
                        'user.data' => [  // snake_case version of userData
                            'name' => 'Snake Case User',
                            'email' => 'snake@example.com',
                            'age' => 35,
                        ],
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(ValidatedUserData $userData): array
                {
                    return [
                        'processed' => true,
                        'user' => $userData->toArray(),
                    ];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->result)->toBe([
                    'processed' => true,
                    'user' => [
                        'name' => 'Snake Case User',
                        'email' => 'snake@example.com',
                        'age' => 35,
                    ],
                ]);
        });
    });

    describe('Regressions', function (): void {
        test('ensures ExceptionMapper is used for all caught exceptions', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'mapper-test',
                'call' => CallData::from([
                    'function' => 'test.mapper',
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(): never
                {
                    throw SimulatedLogicException::customLogicError();
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert - Verify exception was mapped through ExceptionMapper
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->getFirstError())->toBeInstanceOf(ErrorData::class)
                ->and($response->getFirstError()->code)->toBeString()
                ->and($response->getFirstError()->message)->toBeString();
        });

        test('ensures requestObject parameter is always filtered from parameter resolution', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'filter-request-object',
                'call' => CallData::from([
                    'function' => 'test.filterRequestObject',
                    'arguments' => [
                        'requestObject' => 'should-be-ignored',
                        'actualParam' => 'should-be-used',
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(RequestObjectData $requestObject, string $actualParam): array
                {
                    // requestObject should come from injected parameter, not from data
                    return [
                        'method' => $requestObject->getFunction(),
                        'param' => $actualParam,
                    ];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert
            expect($response->result)->toBe([
                'method' => 'test.filterRequestObject',
                'param' => 'should-be-used',
            ]);
        });

        test('ensures error responses always include protocol', function (): void {
            // Arrange
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'error-version',
                'call' => CallData::from([
                    'function' => 'test.errorVersion',
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(): never
                {
                    throw SimulatedException::testError();
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert - Error response should always include protocol
            expect($response->protocol)->toBeInstanceOf(ProtocolData::class)
                ->and($response->getFirstError())->toBeInstanceOf(ErrorData::class);
        });

        test('ensures ValidationException is wrapped in InvalidDataException for Data objects', function (): void {
            // Arrange - Regression test to ensure line 154 is covered
            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => 'validation-wrap',
                'call' => CallData::from([
                    'function' => 'user.register',
                    'arguments' => [
                        'userData' => [
                            'name' => 'A',  // Too short, will trigger validation
                            'email' => '',  // Empty email, will trigger validation
                        ],
                    ],
                ]),
            ]);

            $method = new class() extends AbstractFunction
            {
                public function handle(ValidatedUserData $userData): array
                {
                    return ['registered' => true];
                }
            };

            // Act
            $job = new CallFunction($method, $requestObject);
            $response = $job->handle();

            // Assert - ValidationException should be caught and wrapped in InvalidDataException
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->getFirstError())->toBeInstanceOf(ErrorData::class)
                ->and($response->getFirstError()->code)->toBe(ErrorCode::InvalidArguments->value)
                ->and($response->getFirstError()->message)->toBe('Invalid arguments')
                ->and($response->result)->toBeNull();
        });
    });
});

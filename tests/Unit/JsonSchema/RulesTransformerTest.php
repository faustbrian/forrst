<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Exceptions\JsonSchemaException;
use Cline\Forrst\JsonSchema\RulesTransformer;
use Tests\Support\Fixtures\ComplexTestData;
use Tests\Support\Fixtures\InvalidTestClass;
use Tests\Support\Fixtures\NestedTestData;
use Tests\Support\Fixtures\SimpleTestData;

describe('RulesTransformer', function (): void {
    describe('transform', function (): void {
        describe('Happy Paths', function (): void {
            test('transforms simple required string field rules to JSON Schema', function (): void {
                // Arrange
                $rules = [
                    'name' => 'required|string',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert - Verify JSON Schema structure
                expect($result)
                    ->toBeArray()
                    ->and($result['type'])->toBe('object')
                    ->and($result['properties'])->toHaveKey('name')
                    ->and($result['properties']['name']['type'])->toBe('string')
                    ->and($result['required'])->toContain('name');
            });

            test('transforms multiple fields with different types', function (): void {
                // Arrange
                $rules = [
                    'name' => 'required|string',
                    'age' => 'required|integer',
                    'active' => 'required|boolean',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert - Verify multiple type transformations
                expect($result)
                    ->toBeArray()
                    ->and($result['properties'])->toHaveKeys(['name', 'age', 'active'])
                    ->and($result['properties']['name']['type'])->toBe('string')
                    ->and($result['properties']['age']['type'])->toBe('integer')
                    ->and($result['properties']['active']['type'])->toBe('boolean')
                    ->and($result['required'])->toHaveCount(3);
            });

            test('transforms rules provided as array format', function (): void {
                // Arrange - Laravel validation array syntax
                $rules = [
                    'email' => ['required', 'email', 'max:255'],
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert - Verify array-based rules transform correctly
                expect($result)
                    ->toBeArray()
                    ->and($result['properties']['email']['type'])->toBe('string')
                    ->and($result['properties']['email']['format'])->toBe('email')
                    ->and($result['properties']['email']['maxLength'])->toBe(255)
                    ->and($result['required'])->toContain('email');
            });

            test('merges additional properties into schema', function (): void {
                // Arrange
                $rules = [
                    'name' => 'required|string',
                ];
                $properties = [
                    'name' => [
                        'description' => 'User name field',
                        'example' => 'John Doe',
                    ],
                ];

                // Act
                $result = RulesTransformer::transform($rules, $properties);

                // Assert
                expect($result['properties']['name'])->toHaveKey('description');
                expect($result['properties']['name']['description'])->toBe('User name field');
                expect($result['properties']['name']['example'])->toBe('John Doe');
            });

            test('transforms non-required fields correctly', function (): void {
                // Arrange
                $rules = [
                    'bio' => 'string|nullable|max:1000',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties']['bio'])->toHaveKey('type');
                $bioType = $result['properties']['bio']['type'];
                expect($bioType)->toBeArray();
                expect($bioType)->toContain('string');
                expect($bioType)->toContain('null');
                expect($result['properties']['bio']['maxLength'])->toBe(1_000);
                expect($result['required'])->toBeEmpty();
            });

            test('transforms nested object rules', function (): void {
                // Arrange
                $rules = [
                    'user' => 'required|array',
                    'user.name' => 'required|string',
                    'user.email' => 'required|email',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties'])->toHaveKey('user');
                expect($result['properties']['user']['type'])->toBe('array');
                expect($result['properties'])->toHaveKey('user.name');
                expect($result['properties'])->toHaveKey('user.email');
            });

            test('handles min and max constraints', function (): void {
                // Arrange
                $rules = [
                    'username' => 'required|string|min:3|max:20',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties']['username']['minLength'])->toBe(3);
                expect($result['properties']['username']['maxLength'])->toBe(20);
            });

            test('handles between constraints', function (): void {
                // Arrange
                $rules = [
                    'rating' => 'required|integer|between:1,5',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties']['rating']['minimum'])->toBe(1);
                expect($result['properties']['rating']['maximum'])->toBe(5);
            });

            test('handles in enum constraints', function (): void {
                // Arrange
                $rules = [
                    'status' => 'required|string|in:pending,active,completed',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties']['status']['enum'])->toBeArray();
                expect($result['properties']['status']['enum'])->toContain('pending');
                expect($result['properties']['status']['enum'])->toContain('active');
                expect($result['properties']['status']['enum'])->toContain('completed');
            });

            test('handles regex pattern rules', function (): void {
                // Arrange
                $rules = [
                    'slug' => 'required|string|regex:^[a-z0-9-]+$',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties']['slug']['pattern'])->toBe('^[a-z0-9-]+$');
            });

            test('handles alpha rules', function (): void {
                // Arrange
                $rules = [
                    'letters' => 'required|string|alpha',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties']['letters']['type'])->toBe('string');
                expect($result['properties']['letters']['pattern'])->toBe('^[a-zA-Z]+$');
            });

            test('handles alpha_num rules', function (): void {
                // Arrange
                $rules = [
                    'code' => 'required|string|alpha_num',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties']['code']['type'])->toBe('string');
                expect($result['properties']['code']['pattern'])->toBe('^[a-zA-Z0-9]+$');
            });

            test('handles email format', function (): void {
                // Arrange
                $rules = [
                    'email' => 'required|email',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties']['email']['format'])->toBe('email');
            });

            test('handles url format', function (): void {
                // Arrange
                $rules = [
                    'website' => 'required|url',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties']['website']['format'])->toBe('uri');
            });

            test('handles uuid format', function (): void {
                // Arrange
                $rules = [
                    'identifier' => 'required|uuid',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties']['identifier']['format'])->toBe('uuid');
            });

            test('handles date format', function (): void {
                // Arrange
                $rules = [
                    'birth_date' => 'required|date',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties']['birth_date']['format'])->toBe('date');
            });

            test('handles numeric type', function (): void {
                // Arrange
                $rules = [
                    'price' => 'required|numeric',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties']['price']['type'])->toBe('number');
            });

            test('handles array type', function (): void {
                // Arrange
                $rules = [
                    'tags' => 'required|array',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties']['tags']['type'])->toBe('array');
            });

            test('handles accepted rule', function (): void {
                // Arrange
                $rules = [
                    'terms' => 'accepted',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties']['terms']['enum'])->toBeArray();
                expect($result['properties']['terms']['enum'])->toContain(true);
                expect($result['properties']['terms']['enum'])->toContain('yes');
            });

            test('handles declined rule', function (): void {
                // Arrange
                $rules = [
                    'marketing' => 'declined',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties']['marketing']['enum'])->toBeArray();
                expect($result['properties']['marketing']['enum'])->toContain(false);
                expect($result['properties']['marketing']['enum'])->toContain('no');
            });
        });

        describe('Sad Paths', function (): void {
            test('throws exception for bail rule', function (): void {
                // Arrange - Laravel bail rule cannot be translated to JSON Schema
                $rules = [
                    'name' => 'bail|required|string',
                ];

                // Act & Assert - Verify unsupported rule throws exception
                expect(fn (): array => RulesTransformer::transform($rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for current_password rule', function (): void {
                // Arrange - Laravel authentication rule
                $rules = [
                    'password' => 'current_password',
                ];

                // Act & Assert - Verify authentication rule throws exception
                expect(fn (): array => RulesTransformer::transform($rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for dimensions rule', function (): void {
                // Arrange - Laravel image dimension rule
                $rules = [
                    'image' => 'required|dimensions',
                ];

                // Act & Assert - Verify file-specific rule throws exception
                expect(fn (): array => RulesTransformer::transform($rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for distinct rule', function (): void {
                // Arrange - Laravel array uniqueness rule
                $rules = [
                    'emails' => 'distinct',
                ];

                // Act & Assert - Verify complex validation rule throws exception
                expect(fn (): array => RulesTransformer::transform($rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for exists database rule', function (): void {
                // Arrange - Laravel database existence validation
                $rules = [
                    'user_id' => 'exists:users,id',
                ];

                // Act & Assert - Verify database rule throws exception
                expect(fn (): array => RulesTransformer::transform($rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for file rule', function (): void {
                // Arrange - Laravel file upload rule
                $rules = [
                    'upload' => 'file',
                ];

                // Act & Assert - Verify file upload rule throws exception
                expect(fn (): array => RulesTransformer::transform($rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for image rule', function (): void {
                // Arrange - Laravel image upload rule
                $rules = [
                    'avatar' => 'image',
                ];

                // Act & Assert - Verify image upload rule throws exception
                expect(fn (): array => RulesTransformer::transform($rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for json rule', function (): void {
                // Arrange - Laravel JSON validation rule
                $rules = [
                    'data' => 'json',
                ];

                // Act & Assert - Verify JSON rule throws exception
                expect(fn (): array => RulesTransformer::transform($rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for password rule', function (): void {
                // Arrange - Laravel password rule
                $rules = [
                    'pwd' => 'password',
                ];

                // Act & Assert - Verify password rule throws exception
                expect(fn (): array => RulesTransformer::transform($rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for same rule', function (): void {
                // Arrange - Laravel field comparison rule
                $rules = [
                    'password_confirmation' => 'same:password',
                ];

                // Act & Assert - Verify field comparison rule throws exception
                expect(fn (): array => RulesTransformer::transform($rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for sometimes rule', function (): void {
                // Arrange - Laravel conditional validation rule
                $rules = [
                    'field' => 'sometimes',
                ];

                // Act & Assert - Verify conditional rule throws exception
                expect(fn (): array => RulesTransformer::transform($rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for unique database rule', function (): void {
                // Arrange - Laravel database uniqueness validation
                $rules = [
                    'email' => 'unique:users,email',
                ];

                // Act & Assert - Verify database uniqueness rule throws exception
                expect(fn (): array => RulesTransformer::transform($rules))
                    ->toThrow(JsonSchemaException::class);
            });
        });

        describe('Edge Cases', function (): void {
            test('handles empty rules array', function (): void {
                // Arrange
                $rules = [];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['type'])->toBe('object');
                expect($result['properties'])->toBeEmpty();
                expect($result['required'])->toBeEmpty();
            });

            test('handles empty properties array', function (): void {
                // Arrange
                $rules = [
                    'name' => 'required|string',
                ];
                $properties = [];

                // Act
                $result = RulesTransformer::transform($rules, $properties);

                // Assert
                expect($result['properties']['name']['type'])->toBe('string');
            });

            test('handles field with empty string rules', function (): void {
                // Arrange
                $rules = [
                    'name' => '',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['type'])->toBe('object');
                expect($result['properties'])->toBeEmpty();
            });

            test('handles unicode field names', function (): void {
                // Arrange
                $rules = [
                    'имя' => 'required|string',
                    '名前' => 'required|string',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties'])->toHaveKey('имя');
                expect($result['properties'])->toHaveKey('名前');
            });

            test('handles very long validation rules chain', function (): void {
                // Arrange
                $rules = [
                    'complex' => 'required|string|min:10|max:500|alpha_num|lowercase',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties']['complex']['type'])->toBe('string');
                expect($result['properties']['complex']['minLength'])->toBe(10);
                expect($result['properties']['complex']['maxLength'])->toBe(500);
                expect($result['properties']['complex']['pattern'])->toContain('[a-z]');
            });

            test('handles field names with dots', function (): void {
                // Arrange
                $rules = [
                    'user.name' => 'required|string',
                    'user.email' => 'required|email',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties'])->toHaveKey('user.name');
                expect($result['properties'])->toHaveKey('user.email');
            });

            test('handles mixed required and optional fields', function (): void {
                // Arrange
                $rules = [
                    'required_field' => 'required|string',
                    'optional_field' => 'nullable|string',
                    'another_required' => 'required|integer',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['required'])->toContain('required_field');
                expect($result['required'])->toContain('another_required');
                expect($result['required'])->not->toContain('optional_field');
            });

            test('handles not_in constraint', function (): void {
                // Arrange
                $rules = [
                    'status' => 'required|string|not_in:banned,deleted',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties']['status'])->toHaveKey('not');
                expect($result['properties']['status']['not'])->toHaveKey('enum');
                expect($result['properties']['status']['not']['enum'])->toBeArray();
                expect($result['properties']['status']['not']['enum'])->toContain('banned');
                expect($result['properties']['status']['not']['enum'])->toContain('deleted');
            });

            test('handles lowercase constraint', function (): void {
                // Arrange
                $rules = [
                    'username' => 'required|lowercase',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties']['username']['pattern'])->toBe('^[a-z]*$');
            });

            test('handles uppercase constraint', function (): void {
                // Arrange
                $rules = [
                    'code' => 'required|uppercase',
                ];

                // Act
                $result = RulesTransformer::transform($rules);

                // Assert
                expect($result['properties']['code']['pattern'])->toBe('^[A-Z]*$');
            });
        });
    });

    describe('transformDataObject', function (): void {
        describe('Happy Paths', function (): void {
            test('transforms simple Data class to JSON Schema', function (): void {
                // Arrange - Spatie Data object with single required field
                $dataClass = SimpleTestData::class;

                // Act
                $result = RulesTransformer::transformDataObject($dataClass);

                // Assert - Verify Spatie Data validation attributes transform correctly
                expect($result)
                    ->toBeArray()
                    ->and($result['type'])->toBe('object')
                    ->and($result['properties'])->toHaveKey('name')
                    ->and($result['required'])->toContain('name');
            });

            test('transforms complex Data class with multiple fields', function (): void {
                // Arrange - Spatie Data object with multiple validation attributes
                $dataClass = ComplexTestData::class;

                // Act
                $result = RulesTransformer::transformDataObject($dataClass);

                // Assert - Verify all Spatie validation attributes are transformed
                expect($result)
                    ->toBeArray()
                    ->and($result['properties'])->toHaveKey('name')
                    ->and($result['properties'])->toHaveKey('email')
                    ->and($result['required'])->toContain('name')
                    ->and($result['required'])->toContain('email');
            });

            test('transforms Data class with additional properties', function (): void {
                // Arrange - Spatie Data with custom JSON Schema properties
                $dataClass = SimpleTestData::class;
                $properties = [
                    'name' => [
                        'description' => 'Test name field',
                        'example' => 'Test Example',
                    ],
                ];

                // Act
                $result = RulesTransformer::transformDataObject($dataClass, $properties);

                // Assert - Verify additional properties merge correctly
                expect($result['properties']['name']['description'])
                    ->toBe('Test name field')
                    ->and($result['properties']['name']['example'])->toBe('Test Example');
            });

            test('transforms nested Data class', function (): void {
                // Arrange - Spatie Data with nested Data object relationships
                $dataClass = NestedTestData::class;

                // Act
                $result = RulesTransformer::transformDataObject($dataClass);

                // Assert - Verify nested Spatie Data objects transform correctly
                expect($result)
                    ->toBeArray()
                    ->and($result['properties'])->toHaveKey('title')
                    ->and($result['properties'])->toHaveKey('author');
            });
        });

        describe('Sad Paths', function (): void {
            test('handles non-existent class gracefully', function (): void {
                // Arrange - Invalid class name
                $dataClass = 'App\\Data\\NonExistentData';

                // Act & Assert - Verify PHP Error for non-existent class
                expect(fn (): array => RulesTransformer::transformDataObject($dataClass))
                    ->toThrow(Error::class);
            });

            test('handles non-Data class', function (): void {
                // Arrange - Class not extending Spatie Data
                $dataClass = InvalidTestClass::class;

                // Act & Assert - Verify error for invalid Data class
                expect(fn (): array => RulesTransformer::transformDataObject($dataClass))
                    ->toThrow(Error::class);
            });
        });
    });
});

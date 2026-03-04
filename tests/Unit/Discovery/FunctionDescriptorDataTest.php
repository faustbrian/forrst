<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Discovery\ArgumentData;
use Cline\Forrst\Discovery\DeprecatedData;
use Cline\Forrst\Discovery\ErrorDefinitionData;
use Cline\Forrst\Discovery\ExampleData;
use Cline\Forrst\Discovery\ExternalDocsData;
use Cline\Forrst\Discovery\FunctionDescriptorData;
use Cline\Forrst\Discovery\Query\QueryCapabilitiesData;
use Cline\Forrst\Discovery\ResultDescriptorData;
use Cline\Forrst\Discovery\TagData;

describe('FunctionDescriptorData', function (): void {
    describe('Happy Paths', function (): void {
        test('creates instance with required fields only', function (): void {
            // Arrange
            $name = 'urn:cline:forrst:fn:orders:create';
            $version = '2.0.0';
            $arguments = [
                new ArgumentData(
                    name: 'customer_id',
                    schema: ['type' => 'string'],
                    required: true,
                ),
            ];

            // Act
            $function = new FunctionDescriptorData(
                name: $name,
                version: $version,
                arguments: $arguments,
            );

            // Assert
            expect($function->name)->toBe('urn:cline:forrst:fn:orders:create')
                ->and($function->version)->toBe('2.0.0')
                ->and($function->arguments)->toHaveCount(1)
                ->and($function->arguments[0])->toBeInstanceOf(ArgumentData::class)
                ->and($function->summary)->toBeNull()
                ->and($function->description)->toBeNull()
                ->and($function->tags)->toBeNull()
                ->and($function->result)->toBeNull()
                ->and($function->errors)->toBeNull()
                ->and($function->query)->toBeNull()
                ->and($function->deprecated)->toBeNull()
                ->and($function->sideEffects)->toBeNull()
                ->and($function->discoverable)->toBeTrue()
                ->and($function->examples)->toBeNull()
                ->and($function->externalDocs)->toBeNull();
        });

        test('creates instance with all fields populated', function (): void {
            // Arrange
            $arguments = [
                new ArgumentData(
                    name: 'customer_id',
                    schema: ['type' => 'string'],
                    required: true,
                ),
            ];
            $tags = [
                new TagData(name: 'orders', summary: 'Order operations'),
            ];
            $errors = [
                new ErrorDefinitionData(
                    code: 'CUSTOMER_NOT_FOUND',
                    message: 'Customer not found',
                ),
            ];
            $examples = [
                new ExampleData(
                    name: 'Create simple order',
                    arguments: ['customer_id' => 'cust_123'],
                ),
            ];

            // Act
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:orders:create',
                version: '2.0.0',
                arguments: $arguments,
                summary: 'Create a new order',
                description: 'Creates an order for a customer',
                tags: $tags,
                result: new ResultDescriptorData(resource: 'order'),
                errors: $errors,
                query: new QueryCapabilitiesData(),
                deprecated: new DeprecatedData(reason: 'Use version 3 instead'),
                sideEffects: ['create'],
                discoverable: true,
                examples: $examples,
                externalDocs: new ExternalDocsData(url: 'https://docs.example.com'),
            );

            // Assert
            expect($function->name)->toBe('urn:cline:forrst:fn:orders:create')
                ->and($function->version)->toBe('2.0.0')
                ->and($function->arguments)->toHaveCount(1)
                ->and($function->summary)->toBe('Create a new order')
                ->and($function->description)->toBe('Creates an order for a customer')
                ->and($function->tags)->toHaveCount(1)
                ->and($function->tags[0])->toBeInstanceOf(TagData::class)
                ->and($function->result)->toBeInstanceOf(ResultDescriptorData::class)
                ->and($function->errors)->toHaveCount(1)
                ->and($function->errors[0])->toBeInstanceOf(ErrorDefinitionData::class)
                ->and($function->query)->toBeInstanceOf(QueryCapabilitiesData::class)
                ->and($function->deprecated)->toBeInstanceOf(DeprecatedData::class)
                ->and($function->sideEffects)->toBe(['create'])
                ->and($function->discoverable)->toBeTrue()
                ->and($function->examples)->toHaveCount(1)
                ->and($function->examples[0])->toBeInstanceOf(ExampleData::class)
                ->and($function->externalDocs)->toBeInstanceOf(ExternalDocsData::class);
        });

        test('creates instance with multiple side effects', function (): void {
            // Arrange & Act
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:inventory:sync',
                version: '1.0.0',
                arguments: [],
                sideEffects: ['create', 'update', 'delete'],
            );

            // Assert
            expect($function->sideEffects)->toBe(['create', 'update', 'delete'])
                ->and($function->sideEffects)->toHaveCount(3);
        });

        test('creates instance with empty side effects for read-only function', function (): void {
            // Arrange & Act
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:orders:get',
                version: '2.0.0',
                arguments: [],
                sideEffects: [],
            );

            // Assert
            expect($function->sideEffects)->toBe([])
                ->and($function->sideEffects)->toHaveCount(0);
        });

        test('creates instance with discoverable false', function (): void {
            // Arrange & Act
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:debug:internal',
                version: '1.0.0',
                arguments: [],
                discoverable: false,
            );

            // Assert
            expect($function->discoverable)->toBeFalse();
        });

        test('toArray outputs sideEffects in camelCase', function (): void {
            // Arrange
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:orders:create',
                version: '2.0.0',
                arguments: [],
                sideEffects: ['create', 'update'],
            );

            // Act
            $array = $function->toArray();

            // Assert
            expect($array)->toHaveKey('sideEffects')
                ->and($array['sideEffects'])->toBe(['create', 'update'])
                ->and($array)->not->toHaveKey('side_effects');
        });

        test('toArray outputs externalDocs in camelCase', function (): void {
            // Arrange
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:orders:create',
                version: '2.0.0',
                arguments: [],
                externalDocs: new ExternalDocsData(
                    url: 'https://docs.example.com',
                    description: 'Full documentation',
                ),
            );

            // Act
            $array = $function->toArray();

            // Assert
            expect($array)->toHaveKey('externalDocs')
                ->and($array['externalDocs'])->toBeArray()
                ->and($array['externalDocs']['url'])->toBe('https://docs.example.com')
                ->and($array)->not->toHaveKey('external_docs');
        });

        test('toArray includes all required fields', function (): void {
            // Arrange
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:test:function',
                version: '1.0.0',
                arguments: [],
            );

            // Act
            $array = $function->toArray();

            // Assert
            expect($array)->toHaveKey('name')
                ->and($array)->toHaveKey('version')
                ->and($array)->toHaveKey('arguments')
                ->and($array['name'])->toBe('urn:cline:forrst:fn:test:function')
                ->and($array['version'])->toBe('1.0.0')
                ->and($array['arguments'])->toBe([]);
        });

        test('toArray handles null optional fields', function (): void {
            // Arrange
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:test:function',
                version: '1.0.0',
                arguments: [],
            );

            // Act
            $array = $function->toArray();

            // Assert - Spatie Data may include null fields, so verify they are null
            if (array_key_exists('summary', $array)) {
                expect($array['summary'])->toBeNull();
            }

            if (array_key_exists('description', $array)) {
                expect($array['description'])->toBeNull();
            }

            expect($array)->toHaveKey('name')
                ->and($array)->toHaveKey('version')
                ->and($array)->toHaveKey('arguments');
        });

        test('toArray includes discoverable with default', function (): void {
            // Arrange
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:test:function',
                version: '1.0.0',
                arguments: [],
            );

            // Act
            $array = $function->toArray();

            // Assert
            expect($array)->toHaveKey('discoverable')
                ->and($array['discoverable'])->toBeTrue();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty arguments array', function (): void {
            // Arrange & Act
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:test:function',
                version: '1.0.0',
                arguments: [],
            );

            // Assert
            expect($function->arguments)->toBe([])
                ->and($function->arguments)->toHaveCount(0);
        });

        test('handles multiple arguments', function (): void {
            // Arrange
            $arguments = [
                new ArgumentData(name: 'arg1', schema: ['type' => 'string'], required: true),
                new ArgumentData(name: 'arg2', schema: ['type' => 'integer'], required: false),
                new ArgumentData(name: 'arg3', schema: ['type' => 'boolean']),
            ];

            // Act
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:test:function',
                version: '1.0.0',
                arguments: $arguments,
            );

            // Assert
            expect($function->arguments)->toHaveCount(3)
                ->and($function->arguments[0]->name)->toBe('arg1')
                ->and($function->arguments[1]->name)->toBe('arg2')
                ->and($function->arguments[2]->name)->toBe('arg3');
        });

        test('handles tags as array of arrays', function (): void {
            // Arrange
            $tags = [
                ['name' => 'orders'],
                ['name' => 'admin', 'summary' => 'Admin operations'],
            ];

            // Act
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:test:function',
                version: '1.0.0',
                arguments: [],
                tags: $tags,
            );

            // Assert
            expect($function->tags)->toHaveCount(2)
                ->and($function->tags[0])->toBeArray()
                ->and($function->tags[1])->toBeArray();
        });

        test('handles errors as array of arrays', function (): void {
            // Arrange
            $errors = [
                ['code' => 'NOT_FOUND', 'message' => 'Not found'],
                ['code' => 'INVALID', 'message' => 'Invalid'],
            ];

            // Act
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:test:function',
                version: '1.0.0',
                arguments: [],
                errors: $errors,
            );

            // Assert
            expect($function->errors)->toHaveCount(2)
                ->and($function->errors[0])->toBeArray()
                ->and($function->errors[1])->toBeArray();
        });

        test('handles null side effects', function (): void {
            // Arrange & Act
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:test:function',
                version: '1.0.0',
                arguments: [],
                sideEffects: null,
            );

            // Assert
            expect($function->sideEffects)->toBeNull();
        });

        test('handles function name with dots', function (): void {
            // Arrange & Act
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:api:v2:orders:create',
                version: '1.0.0',
                arguments: [],
            );

            // Assert
            expect($function->name)->toBe('urn:cline:forrst:fn:api:v2:orders:create');
        });

        test('handles version as string with multiple digits', function (): void {
            // Arrange & Act
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:test:function',
                version: '12.0.0',
                arguments: [],
            );

            // Assert
            expect($function->version)->toBe('12.0.0');
        });

        test('toArray with nested Data objects converts correctly', function (): void {
            // Arrange
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:orders:create',
                version: '2.0.0',
                arguments: [
                    new ArgumentData(
                        name: 'customer_id',
                        schema: ['type' => 'string'],
                        required: true,
                    ),
                ],
                result: new ResultDescriptorData(
                    resource: 'order',
                    description: 'The created order',
                ),
            );

            // Act
            $array = $function->toArray();

            // Assert
            expect($array['arguments'])->toBeArray()
                ->and($array['arguments'][0])->toBeArray()
                ->and($array['arguments'][0]['name'])->toBe('customer_id')
                ->and($array['result'])->toBeArray()
                ->and($array['result']['resource'])->toBe('order');
        });
    });

    describe('Sad Paths', function (): void {
        test('validates required name field exists', function (): void {
            // Arrange
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:test:function',
                version: '1.0.0',
                arguments: [],
            );

            // Act & Assert
            expect($function->name)->toBe('urn:cline:forrst:fn:test:function');
        });

        test('validates required version field exists', function (): void {
            // Arrange
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:test:function',
                version: '1.0.0',
                arguments: [],
            );

            // Act & Assert
            expect($function->version)->toBe('1.0.0');
        });

        test('validates required arguments field exists', function (): void {
            // Arrange
            $function = new FunctionDescriptorData(
                name: 'urn:cline:forrst:fn:test:function',
                version: '1.0.0',
                arguments: [],
            );

            // Act & Assert
            expect($function->arguments)->toBeArray();
        });
    });
});

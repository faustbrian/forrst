<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Discovery\ExamplePairingData;
use Cline\Forrst\Exceptions\EmptyFieldException;
use Cline\Forrst\Exceptions\InvalidFieldTypeException;
use Cline\Forrst\Exceptions\InvalidFieldValueException;
use Cline\Forrst\Exceptions\MissingRequiredFieldException;

describe('ExamplePairingData', function (): void {
    describe('Happy Paths', function (): void {
        test('creates instance with required fields only', function (): void {
            // Arrange & Act
            $pairing = new ExamplePairingData(
                name: 'GetSingleEvent',
                params: [
                    ['name' => 'event_id', 'value' => 'evt_123'],
                ],
            );

            // Assert
            expect($pairing->name)->toBe('GetSingleEvent')
                ->and($pairing->params)->toHaveCount(1)
                ->and($pairing->summary)->toBeNull()
                ->and($pairing->description)->toBeNull()
                ->and($pairing->result)->toBeNull();
        });

        test('creates instance with all fields populated', function (): void {
            // Arrange & Act
            $pairing = new ExamplePairingData(
                name: 'ListPublishedEvents',
                params: [
                    ['name' => 'status', 'value' => 'published'],
                    ['name' => 'limit', 'value' => 10],
                ],
                summary: 'Retrieve all published events',
                description: 'Returns a paginated list of events that have been published.',
                result: [
                    'name' => 'EventList',
                    'value' => [
                        ['id' => 'evt_1', 'title' => 'Conference'],
                        ['id' => 'evt_2', 'title' => 'Workshop'],
                    ],
                ],
            );

            // Assert
            expect($pairing->name)->toBe('ListPublishedEvents')
                ->and($pairing->params)->toHaveCount(2)
                ->and($pairing->summary)->toBe('Retrieve all published events')
                ->and($pairing->description)->toBe('Returns a paginated list of events that have been published.')
                ->and($pairing->result)->toHaveKey('name')
                ->and($pairing->result['name'])->toBe('EventList');
        });

        test('creates instance with multiple parameters', function (): void {
            // Arrange & Act
            $pairing = new ExamplePairingData(
                name: 'CreateOrder',
                params: [
                    ['name' => 'customer_id', 'value' => 'cust_abc'],
                    ['name' => 'items', 'value' => [['product_id' => 'prod_1', 'quantity' => 2]]],
                    ['name' => 'shipping_method', 'value' => 'express'],
                ],
            );

            // Assert
            expect($pairing->params)->toHaveCount(3)
                ->and($pairing->params[0]['name'])->toBe('customer_id')
                ->and($pairing->params[1]['value'])->toBeArray();
        });

        test('toArray includes all required fields', function (): void {
            // Arrange
            $pairing = new ExamplePairingData(
                name: 'TestPairing',
                params: [['name' => 'id', 'value' => '123']],
            );

            // Act
            $array = $pairing->toArray();

            // Assert
            expect($array)->toHaveKey('name')
                ->and($array)->toHaveKey('params')
                ->and($array['name'])->toBe('TestPairing')
                ->and($array['params'])->toHaveCount(1);
        });

        test('toArray includes optional fields when provided', function (): void {
            // Arrange
            $pairing = new ExamplePairingData(
                name: 'FullExample',
                params: [['name' => 'id', 'value' => '123']],
                summary: 'A complete example',
                description: 'Detailed description here',
                result: ['name' => 'Result', 'value' => ['success' => true]],
            );

            // Act
            $array = $pairing->toArray();

            // Assert
            expect($array['summary'])->toBe('A complete example')
                ->and($array['description'])->toBe('Detailed description here')
                ->and($array['result'])->toHaveKey('name');
        });
    });

    describe('Sad Paths - Validation Errors', function (): void {
        test('rejects empty params array', function (): void {
            // Arrange & Act & Assert
            expect(fn (): ExamplePairingData => new ExamplePairingData(
                name: 'NoParams',
                params: [],
            ))->toThrow(EmptyFieldException::class, 'params');
        });

        test('rejects param missing name key', function (): void {
            // Arrange & Act & Assert
            expect(fn (): ExamplePairingData => new ExamplePairingData(
                name: 'MissingName',
                params: [
                    ['value' => 123], // Missing 'name'
                ],
            ))->toThrow(MissingRequiredFieldException::class, 'params[0].name');
        });

        test('rejects param missing value key', function (): void {
            // Arrange & Act & Assert
            expect(fn (): ExamplePairingData => new ExamplePairingData(
                name: 'MissingValue',
                params: [
                    ['name' => 'userId'], // Missing 'value'
                ],
            ))->toThrow(MissingRequiredFieldException::class, 'params[0].value');
        });

        test('rejects invalid parameter name format with hyphens', function (): void {
            // Arrange & Act & Assert
            expect(fn (): ExamplePairingData => new ExamplePairingData(
                name: 'InvalidParamName',
                params: [
                    ['name' => 'Invalid-Name', 'value' => 123], // Hyphens not allowed
                ],
            ))->toThrow(InvalidFieldValueException::class, 'camelCase/snake_case');
        });

        test('rejects invalid parameter name starting with uppercase', function (): void {
            // Arrange & Act & Assert
            expect(fn (): ExamplePairingData => new ExamplePairingData(
                name: 'InvalidParamName',
                params: [
                    ['name' => 'UpperCase', 'value' => 123], // Must start with lowercase
                ],
            ))->toThrow(InvalidFieldValueException::class, 'camelCase/snake_case');
        });

        test('rejects invalid parameter name starting with number', function (): void {
            // Arrange & Act & Assert
            expect(fn (): ExamplePairingData => new ExamplePairingData(
                name: 'InvalidParamName',
                params: [
                    ['name' => '1invalid', 'value' => 123], // Must start with lowercase letter
                ],
            ))->toThrow(InvalidFieldValueException::class, 'camelCase/snake_case');
        });

        test('rejects non-array parameter', function (): void {
            // Arrange & Act & Assert
            expect(fn (): ExamplePairingData => new ExamplePairingData(
                name: 'NonArrayParam',
                params: [
                    'not-an-array',
                ],
            ))->toThrow(InvalidFieldTypeException::class, 'array');
        });

        test('rejects param with non-string name', function (): void {
            // Arrange & Act & Assert
            expect(fn (): ExamplePairingData => new ExamplePairingData(
                name: 'NonStringName',
                params: [
                    ['name' => 123, 'value' => 'test'], // Name must be string
                ],
            ))->toThrow(InvalidFieldTypeException::class, 'string');
        });

        test('rejects result missing name key', function (): void {
            // Arrange & Act & Assert
            expect(fn (): ExamplePairingData => new ExamplePairingData(
                name: 'MissingResultName',
                params: [['name' => 'param', 'value' => 1]],
                result: ['value' => 'data'], // Missing 'name'
            ))->toThrow(MissingRequiredFieldException::class, 'result.name');
        });

        test('rejects result missing value key', function (): void {
            // Arrange & Act & Assert
            expect(fn (): ExamplePairingData => new ExamplePairingData(
                name: 'MissingResultValue',
                params: [['name' => 'param', 'value' => 1]],
                result: ['name' => 'result'], // Missing 'value'
            ))->toThrow(MissingRequiredFieldException::class, 'result.value');
        });

        test('rejects result with non-string name', function (): void {
            // Arrange & Act & Assert
            expect(fn (): ExamplePairingData => new ExamplePairingData(
                name: 'NonStringResultName',
                params: [['name' => 'param', 'value' => 1]],
                result: ['name' => 123, 'value' => 'data'], // Name must be string
            ))->toThrow(InvalidFieldTypeException::class, 'string');
        });
    });

    describe('Edge Cases', function (): void {
        test('accepts valid camelCase parameter names', function (): void {
            // Arrange & Act
            $pairing = new ExamplePairingData(
                name: 'CamelCaseParams',
                params: [
                    ['name' => 'userId', 'value' => 123],
                    ['name' => 'isEnabled', 'value' => true],
                    ['name' => 'maxRetries', 'value' => 5],
                ],
            );

            // Assert
            expect($pairing->params)->toHaveCount(3);
        });

        test('accepts valid snake_case parameter names', function (): void {
            // Arrange & Act
            $pairing = new ExamplePairingData(
                name: 'SnakeCaseParams',
                params: [
                    ['name' => 'user_id', 'value' => 123],
                    ['name' => 'is_enabled', 'value' => true],
                    ['name' => 'max_retries', 'value' => 5],
                ],
            );

            // Assert
            expect($pairing->params)->toHaveCount(3);
        });

        test('handles params with null values', function (): void {
            // Arrange & Act
            $pairing = new ExamplePairingData(
                name: 'NullParam',
                params: [
                    ['name' => 'optionalField', 'value' => null],
                ],
            );

            // Assert
            expect($pairing->params[0]['value'])->toBeNull();
        });

        test('handles params with complex nested values', function (): void {
            // Arrange
            $complexValue = [
                'items' => [
                    ['id' => 1, 'nested' => ['deep' => 'value']],
                    ['id' => 2, 'nested' => ['deep' => 'another']],
                ],
                'status' => ['in' => ['active', 'pending']],
                'created' => ['gt' => '2024-01-01'],
            ];

            // Act
            $pairing = new ExamplePairingData(
                name: 'ComplexParams',
                params: [['name' => 'filter', 'value' => $complexValue]],
            );

            // Assert
            expect($pairing->params[0]['value']['items'])->toHaveCount(2)
                ->and($pairing->params[0]['value']['items'][0]['nested']['deep'])->toBe('value')
                ->and($pairing->params[0]['value']['status']['in'])->toHaveCount(2);
        });

        test('handles result with null value', function (): void {
            // Arrange & Act
            $pairing = new ExamplePairingData(
                name: 'NullResult',
                params: [['name' => 'id', 'value' => '123']],
                result: ['name' => 'VoidResult', 'value' => null],
            );

            // Assert
            expect($pairing->result['value'])->toBeNull();
        });

        test('handles params with boolean and numeric values', function (): void {
            // Arrange & Act
            $pairing = new ExamplePairingData(
                name: 'TypedParams',
                params: [
                    ['name' => 'enabled', 'value' => true],
                    ['name' => 'count', 'value' => 0],
                    ['name' => 'disabled', 'value' => false],
                ],
            );

            // Assert
            expect($pairing->params[0]['value'])->toBeTrue()
                ->and($pairing->params[1]['value'])->toBe(0)
                ->and($pairing->params[2]['value'])->toBeFalse();
        });

        test('notification-style pairing without result', function (): void {
            // Arrange & Act
            $pairing = new ExamplePairingData(
                name: 'SendEmail',
                params: [
                    ['name' => 'recipient', 'value' => 'user@example.com'],
                    ['name' => 'subject', 'value' => 'Test'],
                ],
            );

            // Assert
            expect($pairing->result)->toBeNull();
        });

        test('handles complex result structures', function (): void {
            // Arrange & Act
            $pairing = new ExamplePairingData(
                name: 'ComplexResult',
                params: [['name' => 'userId', 'value' => 123]],
                result: [
                    'name' => 'user',
                    'value' => [
                        'id' => 123,
                        'name' => 'John Doe',
                        'permissions' => ['read', 'write'],
                        'metadata' => ['created' => '2024-01-01', 'verified' => true],
                    ],
                ],
            );

            // Assert
            expect($pairing->result['value'])->toBeArray()
                ->and($pairing->result['value']['permissions'])->toHaveCount(2);
        });
    });
});

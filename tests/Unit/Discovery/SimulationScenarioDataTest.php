<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Discovery\SimulationScenarioData;
use Cline\Forrst\Exceptions\EmptyArrayException;
use Cline\Forrst\Exceptions\EmptyFieldException;
use Cline\Forrst\Exceptions\InvalidFieldValueException;

describe('SimulationScenarioData', function (): void {
    describe('Happy Paths', function (): void {
        test('validates output and error are mutually exclusive', function (): void {
            // Arrange & Act & Assert
            expect(fn (): SimulationScenarioData => new SimulationScenarioData(
                name: 'test',
                input: ['user_id' => 123],
                output: ['id' => 123],
                error: ['code' => 'ERROR', 'message' => 'Error'],
            ))->toThrow(
                InvalidFieldValueException::class,
                'Cannot specify both "output" and "error"',
            );
        });

        test('creates instance with only required parameters', function (): void {
            // Arrange
            $name = 'minimal_scenario';
            $input = ['id' => 1];

            // Act
            $scenario = new SimulationScenarioData(
                name: $name,
                input: $input,
            );

            // Assert
            expect($scenario->name)->toBe($name)
                ->and($scenario->input)->toBe($input)
                ->and($scenario->output)->toBeNull()
                ->and($scenario->description)->toBeNull()
                ->and($scenario->error)->toBeNull()
                ->and($scenario->metadata)->toBeNull();
        });

        test('creates success scenario using factory method with description', function (): void {
            // Arrange
            $name = 'user_created';
            $input = ['name' => 'Alice', 'email' => 'alice@example.com'];
            $output = ['id' => 456, 'name' => 'Alice', 'status' => 'active'];
            $description = 'User successfully created with valid data';

            // Act
            $scenario = SimulationScenarioData::success(
                name: $name,
                input: $input,
                output: $output,
                description: $description,
            );

            // Assert
            expect($scenario->name)->toBe($name)
                ->and($scenario->input)->toBe($input)
                ->and($scenario->output)->toBe($output)
                ->and($scenario->description)->toBe($description)
                ->and($scenario->error)->toBeNull()
                ->and($scenario->metadata)->toBeNull();
        });

        test('creates success scenario using factory method without description', function (): void {
            // Arrange
            $name = 'simple_success';
            $input = ['action' => 'ping'];
            $output = ['status' => 'pong'];

            // Act
            $scenario = SimulationScenarioData::success(
                name: $name,
                input: $input,
                output: $output,
            );

            // Assert
            expect($scenario->name)->toBe($name)
                ->and($scenario->input)->toBe($input)
                ->and($scenario->output)->toBe($output)
                ->and($scenario->description)->toBeNull()
                ->and($scenario->error)->toBeNull();
        });

        test('creates error scenario using factory method with error code and message', function (): void {
            // Arrange
            $name = 'user_not_found';
            $input = ['user_id' => 999];
            $code = 'NOT_FOUND';
            $message = 'User not found';
            $description = 'Returns 404 when user ID does not exist';

            // Act
            $scenario = SimulationScenarioData::error(
                name: $name,
                input: $input,
                code: $code,
                message: $message,
                description: $description,
            );

            // Assert
            expect($scenario->name)->toBe($name)
                ->and($scenario->input)->toBe($input)
                ->and($scenario->error)->toBe(['code' => $code, 'message' => $message])
                ->and($scenario->description)->toBe($description)
                ->and($scenario->output)->toBeNull()
                ->and($scenario->metadata)->toBeNull();
        });

        test('creates error scenario using factory method with error data included', function (): void {
            // Arrange
            $name = 'validation_error';
            $input = ['name' => '', 'email' => 'invalid'];
            $code = 'VALIDATION_FAILED';
            $message = 'Validation failed';
            $data = ['name' => ['Name is required'], 'email' => ['Email format is invalid']];
            $description = 'Returns validation errors for invalid input';

            // Act
            $scenario = SimulationScenarioData::error(
                name: $name,
                input: $input,
                code: $code,
                message: $message,
                data: $data,
                description: $description,
            );

            // Assert
            expect($scenario->name)->toBe($name)
                ->and($scenario->input)->toBe($input)
                ->and($scenario->error)->toBe(['code' => $code, 'message' => $message, 'details' => $data])
                ->and($scenario->description)->toBe($description)
                ->and($scenario->output)->toBeNull();
        });

        test('handles complex nested input structures', function (): void {
            // Arrange
            $name = 'complex_input';
            $input = [
                'user' => [
                    'profile' => [
                        'name' => 'John',
                        'settings' => ['theme' => 'dark', 'notifications' => true],
                    ],
                    'roles' => ['admin', 'editor'],
                ],
                'metadata' => ['timestamp' => 1_234_567_890],
            ];
            $output = ['processed' => true];

            // Act
            $scenario = SimulationScenarioData::success(
                name: $name,
                input: $input,
                output: $output,
            );

            // Assert
            expect($scenario->input)->toBe($input)
                ->and($scenario->input['user']['profile']['settings']['theme'])->toBe('dark')
                ->and($scenario->input['user']['roles'])->toBe(['admin', 'editor']);
        });

        test('handles complex nested output structures', function (): void {
            // Arrange
            $name = 'complex_output';
            $input = ['query' => 'test'];
            $output = [
                'results' => [
                    ['id' => 1, 'score' => 0.95, 'metadata' => ['type' => 'exact']],
                    ['id' => 2, 'score' => 0.78, 'metadata' => ['type' => 'partial']],
                ],
                'pagination' => ['total' => 2, 'page' => 1, 'per_page' => 10],
            ];

            // Act
            $scenario = SimulationScenarioData::success(
                name: $name,
                input: $input,
                output: $output,
            );

            // Assert
            expect($scenario->output)->toBe($output)
                ->and($scenario->output['results'])->toHaveCount(2)
                ->and($scenario->output['results'][0]['score'])->toBe(0.95)
                ->and($scenario->output['pagination']['total'])->toBe(2);
        });

        test('handles unicode characters in name field', function (): void {
            // Arrange
            $name = 'успех_сценарий_🎉';
            $input = ['text' => 'test'];
            $output = ['result' => 'ok'];

            // Act
            $scenario = SimulationScenarioData::success(
                name: $name,
                input: $input,
                output: $output,
            );

            // Assert
            expect($scenario->name)->toBe($name)
                ->and($scenario->name)->toContain('🎉');
        });

        test('handles unicode characters in description field', function (): void {
            // Arrange
            $name = 'unicode_test';
            $input = ['language' => 'ja'];
            $output = ['greeting' => 'こんにちは'];
            $description = 'Returns greeting in Japanese: こんにちは 世界 🌍';

            // Act
            $scenario = SimulationScenarioData::success(
                name: $name,
                input: $input,
                output: $output,
                description: $description,
            );

            // Assert
            expect($scenario->description)->toBe($description)
                ->and($scenario->description)->toContain('こんにちは')
                ->and($scenario->description)->toContain('🌍');
        });

        test('handles different output types with scalar values', function (): void {
            // Arrange
            $scenarios = [
                ['name' => 'string_output', 'input' => ['type' => 'string'], 'output' => 'result'],
                ['name' => 'int_output', 'input' => ['type' => 'int'], 'output' => 42],
                ['name' => 'float_output', 'input' => ['type' => 'float'], 'output' => 3.14],
                ['name' => 'bool_output', 'input' => ['type' => 'bool'], 'output' => true],
            ];

            // Act & Assert
            foreach ($scenarios as $data) {
                $scenario = SimulationScenarioData::success(
                    name: $data['name'],
                    input: $data['input'],
                    output: $data['output'],
                );

                expect($scenario->output)->toBe($data['output']);
            }
        });

        test('rejects empty input array', function (): void {
            // Arrange & Act & Assert
            expect(fn (): SimulationScenarioData => SimulationScenarioData::success(
                name: 'test',
                input: [],
                output: ['result' => 'ok'],
            ))->toThrow(EmptyArrayException::class, 'input cannot be empty');
        });
    });

    describe('Edge Cases', function (): void {
        test('handles null output value explicitly', function (): void {
            // Arrange
            $name = 'null_output';
            $input = ['delete' => true];
            $output = null;

            // Act
            $scenario = new SimulationScenarioData(
                name: $name,
                input: $input,
                output: $output,
            );

            // Assert
            expect($scenario->output)->toBeNull();
        });

        test('handles error scenario without optional data parameter', function (): void {
            // Arrange
            $name = 'simple_error';
            $input = ['resource' => 'missing'];
            $code = 'INTERNAL_ERROR';
            $message = 'Internal server error';

            // Act
            $scenario = SimulationScenarioData::error(
                name: $name,
                input: $input,
                code: $code,
                message: $message,
            );

            // Assert
            expect($scenario->error)->toBe(['code' => $code, 'message' => $message])
                ->and($scenario->error)->not->toHaveKey('data');
        });

        test('rejects empty string as error code', function (): void {
            // Arrange & Act & Assert
            expect(fn (): SimulationScenarioData => SimulationScenarioData::error(
                name: 'test',
                input: ['test' => true],
                code: '',
                message: 'No error',
            ))->toThrow(InvalidFieldValueException::class, 'error.code is invalid');
        });

        test('handles lowercase error codes', function (): void {
            // Arrange
            $name = 'lowercase_error';
            $input = ['action' => 'fail'];
            $code = 'CUSTOM_ERROR';
            $message = 'Custom error';

            // Act
            $scenario = SimulationScenarioData::error(
                name: $name,
                input: $input,
                code: $code,
                message: $message,
            );

            // Assert
            expect($scenario->error['code'])->toBe('CUSTOM_ERROR');
        });

        test('rejects empty string as name', function (): void {
            // Arrange & Act & Assert
            expect(fn (): SimulationScenarioData => new SimulationScenarioData(
                name: '',
                input: ['test' => true],
            ))->toThrow(EmptyFieldException::class, 'name cannot be empty');
        });

        test('handles very long scenario name', function (): void {
            // Arrange
            $name = str_repeat('a', 1_000);
            $input = ['test' => true];
            $output = ['result' => true];

            // Act
            $scenario = SimulationScenarioData::success(
                name: $name,
                input: $input,
                output: $output,
            );

            // Assert
            expect($scenario->name)->toHaveLength(1_000);
        });

        test('handles very long description', function (): void {
            // Arrange
            $name = 'long_desc';
            $input = ['test' => true];
            $output = ['result' => true];
            $description = str_repeat('Long description. ', 100);

            // Act
            $scenario = SimulationScenarioData::success(
                name: $name,
                input: $input,
                output: $output,
                description: $description,
            );

            // Assert
            expect($scenario->description)->toContain('Long description.')
                ->and(mb_strlen((string) $scenario->description))->toBeGreaterThan(1_000);
        });

        test('handles special characters in error message', function (): void {
            // Arrange
            $name = 'special_chars';
            $input = ['text' => 'test'];
            $code = 'BAD_REQUEST';
            $message = "Error with 'quotes', \"double quotes\", and\nnewlines\ttabs";

            // Act
            $scenario = SimulationScenarioData::error(
                name: $name,
                input: $input,
                code: $code,
                message: $message,
            );

            // Assert
            expect($scenario->error['message'])->toBe($message)
                ->and($scenario->error['message'])->toContain("'quotes'")
                ->and($scenario->error['message'])->toContain("\n");
        });

        test('handles null values within nested input arrays', function (): void {
            // Arrange
            $name = 'null_in_array';
            $input = [
                'values' => [null, 'string', 123, null],
                'nested' => ['key' => null],
            ];
            $output = ['processed' => true];

            // Act
            $scenario = SimulationScenarioData::success(
                name: $name,
                input: $input,
                output: $output,
            );

            // Assert
            expect($scenario->input['values'][0])->toBeNull()
                ->and($scenario->input['values'][3])->toBeNull()
                ->and($scenario->input['nested']['key'])->toBeNull();
        });

        test('handles metadata with various data types', function (): void {
            // Arrange
            $name = 'metadata_test';
            $input = ['action' => 'test'];
            $metadata = [
                'timing' => 150,
                'cached' => true,
                'source' => 'database',
                'tags' => ['fast', 'reliable'],
                'config' => ['retry' => 3, 'timeout' => null],
            ];

            // Act
            $scenario = new SimulationScenarioData(
                name: $name,
                input: $input,
                metadata: $metadata,
            );

            // Assert
            expect($scenario->metadata)->toBe($metadata)
                ->and($scenario->metadata['timing'])->toBe(150)
                ->and($scenario->metadata['cached'])->toBeTrue()
                ->and($scenario->metadata['tags'])->toHaveCount(2)
                ->and($scenario->metadata['config']['timeout'])->toBeNull();
        });

        test('handles input with numeric string keys', function (): void {
            // Arrange
            $name = 'numeric_keys';
            $input = ['0' => 'first', '1' => 'second', '10' => 'tenth'];
            $output = ['result' => 'ok'];

            // Act
            $scenario = SimulationScenarioData::success(
                name: $name,
                input: $input,
                output: $output,
            );

            // Assert
            expect($scenario->input)->toHaveKey('0')
                ->and($scenario->input)->toHaveKey('1')
                ->and($scenario->input)->toHaveKey('10')
                ->and($scenario->input['0'])->toBe('first');
        });
    });
});

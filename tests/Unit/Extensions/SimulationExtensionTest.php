<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Contracts\FunctionInterface;
use Cline\Forrst\Contracts\SimulatableInterface;
use Cline\Forrst\Data\CallData;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Discovery\SimulationScenarioData;
use Cline\Forrst\Extensions\ExtensionUrn;
use Cline\Forrst\Extensions\SimulationExtension;

describe('SimulationExtension', function (): void {
    describe('Happy Paths', function (): void {
        test('getUrn returns correct URN constant', function (): void {
            // Arrange
            $extension = new SimulationExtension();

            // Act
            $urn = $extension->getUrn();

            // Assert
            expect($urn)->toBe(ExtensionUrn::Simulation->value)
                ->and($urn)->toBe('urn:cline:forrst:ext:simulation');
        });

        test('isErrorFatal returns true', function (): void {
            // Arrange
            $extension = new SimulationExtension();

            // Act
            $result = $extension->isErrorFatal();

            // Assert
            expect($result)->toBeTrue();
        });

        test('isEnabled returns true when enabled option is true', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $options = ['enabled' => true];

            // Act
            $result = $extension->isEnabled($options);

            // Assert
            expect($result)->toBeTrue();
        });

        test('isEnabled returns false when enabled option is false', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $options = ['enabled' => false];

            // Act
            $result = $extension->isEnabled($options);

            // Assert
            expect($result)->toBeFalse();
        });

        test('shouldListScenarios returns true when list_scenarios is true', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $options = ['list_scenarios' => true];

            // Act
            $result = $extension->shouldListScenarios($options);

            // Assert
            expect($result)->toBeTrue();
        });

        test('shouldListScenarios returns false when list_scenarios is false', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $options = ['list_scenarios' => false];

            // Act
            $result = $extension->shouldListScenarios($options);

            // Assert
            expect($result)->toBeFalse();
        });

        test('getRequestedScenario returns scenario name when provided as string', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $options = ['scenario' => 'success'];

            // Act
            $result = $extension->getRequestedScenario($options);

            // Assert
            expect($result)->toBe('success');
        });

        test('getRequestedScenario returns null when scenario is not provided', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $options = [];

            // Act
            $result = $extension->getRequestedScenario($options);

            // Assert
            expect($result)->toBeNull();
        });

        test('supportsSimulation returns true for SimulatableInterface', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $function = Mockery::mock(SimulatableInterface::class);

            // Act
            $result = $extension->supportsSimulation($function);

            // Assert
            expect($result)->toBeTrue();
        });

        test('supportsSimulation returns false for regular FunctionInterface', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $function = Mockery::mock(FunctionInterface::class);

            // Act
            $result = $extension->supportsSimulation($function);

            // Assert
            expect($result)->toBeFalse();
        });

        test('findScenario returns matching scenario by name', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $scenarios = [
                SimulationScenarioData::success('success', ['id' => 1], ['user' => 'John']),
                SimulationScenarioData::success('not_found', ['id' => 999], null),
            ];
            $function = Mockery::mock(SimulatableInterface::class);
            $function->shouldReceive('getSimulationScenarios')->andReturn($scenarios);

            // Act
            $result = $extension->findScenario($function, 'success');

            // Assert
            expect($result)->toBeInstanceOf(SimulationScenarioData::class)
                ->and($result->name)->toBe('success');
        });

        test('findScenario returns null when scenario not found', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $scenarios = [
                SimulationScenarioData::success('success', ['id' => 1], ['user' => 'John']),
            ];
            $function = Mockery::mock(SimulatableInterface::class);
            $function->shouldReceive('getSimulationScenarios')->andReturn($scenarios);

            // Act
            $result = $extension->findScenario($function, 'nonexistent');

            // Assert
            expect($result)->toBeNull();
        });

        test('getScenarioToExecute returns requested scenario when specified', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $scenarios = [
                SimulationScenarioData::success('default', ['id' => 1], ['user' => 'Default']),
                SimulationScenarioData::success('custom', ['id' => 2], ['user' => 'Custom']),
            ];
            $function = Mockery::mock(SimulatableInterface::class);
            $function->shouldReceive('getSimulationScenarios')->andReturn($scenarios);
            $function->shouldReceive('getDefaultScenario')->andReturn('default');
            $options = ['scenario' => 'custom'];

            // Act
            $result = $extension->getScenarioToExecute($function, $options);

            // Assert
            expect($result)->toBeInstanceOf(SimulationScenarioData::class)
                ->and($result->name)->toBe('custom');
        });

        test('getScenarioToExecute returns default scenario when none specified', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $scenarios = [
                SimulationScenarioData::success('default', ['id' => 1], ['user' => 'Default']),
                SimulationScenarioData::success('custom', ['id' => 2], ['user' => 'Custom']),
            ];
            $function = Mockery::mock(SimulatableInterface::class);
            $function->shouldReceive('getSimulationScenarios')->andReturn($scenarios);
            $function->shouldReceive('getDefaultScenario')->andReturn('default');
            $options = [];

            // Act
            $result = $extension->getScenarioToExecute($function, $options);

            // Assert
            expect($result)->toBeInstanceOf(SimulationScenarioData::class)
                ->and($result->name)->toBe('default');
        });

        test('buildSimulatedResponse creates successful simulation response', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0001',
                call: new CallData(function: 'getUser', arguments: ['id' => 123]),
            );
            $scenario = SimulationScenarioData::success(
                'success',
                ['id' => 123],
                ['id' => 123, 'name' => 'John Doe', 'email' => 'john@example.com'],
                'User successfully retrieved',
            );

            // Act
            $response = $extension->buildSimulatedResponse($request, $scenario);

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->id)->toBe('01JFEX0001')
                ->and($response->result)->toBe(['id' => 123, 'name' => 'John Doe', 'email' => 'john@example.com'])
                ->and($response->extensions)->toHaveCount(1);

            $ext = $response->extensions[0];
            expect($ext->urn)->toBe(ExtensionUrn::Simulation->value)
                ->and($ext->data)->toHaveKey('simulated', true)
                ->and($ext->data)->toHaveKey('scenario', 'success');
        });

        test('buildSimulatedErrorResponse creates error simulation response with default error', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0002',
                call: new CallData(function: 'deleteUser', arguments: ['id' => 999]),
            );
            $scenario = SimulationScenarioData::success('not_found', ['id' => 999], null);

            // Act
            $response = $extension->buildSimulatedErrorResponse($request, $scenario);

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->id)->toBe('01JFEX0002')
                ->and($response->errors[0])->not->toBeNull();

            expect($response->errors[0]->code)->toBe('SIMULATED_ERROR')
                ->and($response->errors[0]->message)->toBe('Simulated error');

            $ext = $response->extensions[0];
            expect($ext->urn)->toBe(ExtensionUrn::Simulation->value)
                ->and($ext->data)->toHaveKey('simulated', true)
                ->and($ext->data)->toHaveKey('scenario', 'not_found');
        });

        test('buildSimulatedErrorResponse creates error simulation response with custom error', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0003',
                call: new CallData(function: 'deleteUser', arguments: ['id' => 999]),
            );
            $scenario = SimulationScenarioData::error(
                'not_found',
                ['id' => 999],
                'NOT_FOUND',
                'User not found',
                ['resource' => 'user', 'id' => 999],
                'Simulates user not found error',
            );

            // Act
            $response = $extension->buildSimulatedErrorResponse($request, $scenario);

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->id)->toBe('01JFEX0003')
                ->and($response->errors[0])->not->toBeNull();

            expect($response->errors[0]->code)->toBe('NOT_FOUND')
                ->and($response->errors[0]->message)->toBe('User not found')
                ->and($response->errors[0]->details)->toBe(['resource' => 'user', 'id' => 999]);

            $ext = $response->extensions[0];
            expect($ext->data)->toHaveKey('simulated', true)
                ->and($ext->data)->toHaveKey('scenario', 'not_found');
        });

        test('buildScenarioListResponse lists all available scenarios', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0004',
                call: new CallData(function: 'getUser'),
            );
            $scenarios = [
                SimulationScenarioData::success('success', ['id' => 1], ['user' => 'John'], 'User found'),
                SimulationScenarioData::error('not_found', ['id' => 999], 'NOT_FOUND', 'Not found', null, 'User not found'),
                SimulationScenarioData::success('empty', ['id' => 2], null, 'Empty response'),
            ];
            $function = Mockery::mock(SimulatableInterface::class);
            $function->shouldReceive('getSimulationScenarios')->andReturn($scenarios);
            $function->shouldReceive('getDefaultScenario')->andReturn('success');

            // Act
            $response = $extension->buildScenarioListResponse($request, $function);

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->id)->toBe('01JFEX0004')
                ->and($response->result)->toBeNull()
                ->and($response->extensions)->toHaveCount(1);

            $ext = $response->extensions[0];
            expect($ext->urn)->toBe(ExtensionUrn::Simulation->value)
                ->and($ext->data)->toHaveKey('simulated', false)
                ->and($ext->data)->toHaveKey('available_scenarios');

            $availableScenarios = $ext->data['available_scenarios'];
            expect($availableScenarios)->toHaveCount(3);

            expect($availableScenarios[0])->toBe([
                'name' => 'success',
                'description' => 'User found',
                'is_error' => false,
                'is_default' => true,
            ]);

            expect($availableScenarios[1])->toBe([
                'name' => 'not_found',
                'description' => 'User not found',
                'is_error' => true,
                'is_default' => false,
            ]);

            expect($availableScenarios[2])->toBe([
                'name' => 'empty',
                'description' => 'Empty response',
                'is_error' => false,
                'is_default' => false,
            ]);
        });

        test('buildUnsupportedResponse creates error for non-simulatable function', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0005',
                call: new CallData(function: 'unsupportedFunction'),
            );

            // Act
            $response = $extension->buildUnsupportedResponse($request, 'unsupportedFunction');

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->id)->toBe('01JFEX0005')
                ->and($response->errors[0])->not->toBeNull();

            expect($response->errors[0]->code)->toBe('SIMULATION_NOT_SUPPORTED')
                ->and($response->errors[0]->message)->toBe("Function 'unsupportedFunction' does not support simulation");

            $ext = $response->extensions[0];
            expect($ext->urn)->toBe(ExtensionUrn::Simulation->value)
                ->and($ext->data)->toHaveKey('simulated', false)
                ->and($ext->data)->toHaveKey('error', 'unsupported');
        });

        test('buildScenarioNotFoundResponse creates error for missing scenario', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0006',
                call: new CallData(function: 'getUser'),
            );

            // Act
            $response = $extension->buildScenarioNotFoundResponse($request, 'nonexistent');

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->id)->toBe('01JFEX0006')
                ->and($response->errors[0])->not->toBeNull();

            expect($response->errors[0]->code)->toBe('SIMULATION_SCENARIO_NOT_FOUND')
                ->and($response->errors[0]->message)->toBe("Simulation scenario 'nonexistent' not found");

            $ext = $response->extensions[0];
            expect($ext->urn)->toBe(ExtensionUrn::Simulation->value)
                ->and($ext->data)->toHaveKey('simulated', false)
                ->and($ext->data)->toHaveKey('error', 'scenario_not_found')
                ->and($ext->data)->toHaveKey('requested_scenario', 'nonexistent');
        });
    });

    describe('Edge Cases', function (): void {
        test('isEnabled returns false when enabled option is missing', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $options = [];

            // Act
            $result = $extension->isEnabled($options);

            // Assert
            expect($result)->toBeFalse();
        });

        test('isEnabled returns false when options is null', function (): void {
            // Arrange
            $extension = new SimulationExtension();

            // Act
            $result = $extension->isEnabled(null);

            // Assert
            expect($result)->toBeFalse();
        });

        test('isEnabled returns false for non-boolean true values', function (): void {
            // Arrange
            $extension = new SimulationExtension();

            // Act & Assert
            expect($extension->isEnabled(['enabled' => 1]))->toBeFalse();
            expect($extension->isEnabled(['enabled' => 'true']))->toBeFalse();
            expect($extension->isEnabled(['enabled' => 'yes']))->toBeFalse();
        });

        test('shouldListScenarios returns false when option is missing', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $options = [];

            // Act
            $result = $extension->shouldListScenarios($options);

            // Assert
            expect($result)->toBeFalse();
        });

        test('shouldListScenarios returns false when options is null', function (): void {
            // Arrange
            $extension = new SimulationExtension();

            // Act
            $result = $extension->shouldListScenarios(null);

            // Assert
            expect($result)->toBeFalse();
        });

        test('shouldListScenarios returns false for non-boolean true values', function (): void {
            // Arrange
            $extension = new SimulationExtension();

            // Act & Assert
            expect($extension->shouldListScenarios(['list_scenarios' => 1]))->toBeFalse();
            expect($extension->shouldListScenarios(['list_scenarios' => 'true']))->toBeFalse();
        });

        test('getRequestedScenario returns null when options is null', function (): void {
            // Arrange
            $extension = new SimulationExtension();

            // Act
            $result = $extension->getRequestedScenario(null);

            // Assert
            expect($result)->toBeNull();
        });

        test('getRequestedScenario returns null for non-string scenario values', function (): void {
            // Arrange
            $extension = new SimulationExtension();

            // Act & Assert
            expect($extension->getRequestedScenario(['scenario' => 123]))->toBeNull();
            expect($extension->getRequestedScenario(['scenario' => true]))->toBeNull();
            expect($extension->getRequestedScenario(['scenario' => ['name' => 'test']]))->toBeNull();
        });

        test('findScenario returns null when function has no scenarios', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $function = Mockery::mock(SimulatableInterface::class);
            $function->shouldReceive('getSimulationScenarios')->andReturn([]);

            // Act
            $result = $extension->findScenario($function, 'any');

            // Assert
            expect($result)->toBeNull();
        });

        test('findScenario handles scenario names with special characters', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $scenarios = [
                SimulationScenarioData::success('user-not-found', ['id' => 1], null),
                SimulationScenarioData::success('validation_error', ['id' => 2], null),
                SimulationScenarioData::success('success.with.dots', ['id' => 3], ['result' => 'ok']),
            ];
            $function = Mockery::mock(SimulatableInterface::class);
            $function->shouldReceive('getSimulationScenarios')->andReturn($scenarios);

            // Act
            $result = $extension->findScenario($function, 'success.with.dots');

            // Assert
            expect($result)->toBeInstanceOf(SimulationScenarioData::class)
                ->and($result->name)->toBe('success.with.dots');
        });

        test('getScenarioToExecute returns null when default scenario not found', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $scenarios = [
                SimulationScenarioData::success('scenario1', ['id' => 1], ['data' => 'test']),
            ];
            $function = Mockery::mock(SimulatableInterface::class);
            $function->shouldReceive('getSimulationScenarios')->andReturn($scenarios);
            $function->shouldReceive('getDefaultScenario')->andReturn('nonexistent_default');
            $options = [];

            // Act
            $result = $extension->getScenarioToExecute($function, $options);

            // Assert
            expect($result)->toBeNull();
        });

        test('getScenarioToExecute returns null when requested scenario not found', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $scenarios = [
                SimulationScenarioData::success('default', ['id' => 1], ['data' => 'test']),
            ];
            $function = Mockery::mock(SimulatableInterface::class);
            $function->shouldReceive('getSimulationScenarios')->andReturn($scenarios);
            $function->shouldReceive('getDefaultScenario')->andReturn('default');
            $options = ['scenario' => 'nonexistent'];

            // Act
            $result = $extension->getScenarioToExecute($function, $options);

            // Assert
            expect($result)->toBeNull();
        });

        test('buildSimulatedResponse handles null output', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0007',
                call: new CallData(function: 'deleteUser'),
            );
            $scenario = SimulationScenarioData::success('deleted', ['id' => 123], null);

            // Act
            $response = $extension->buildSimulatedResponse($request, $scenario);

            // Assert
            expect($response->result)->toBeNull();
        });

        test('buildSimulatedResponse handles complex nested output', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0008',
                call: new CallData(function: 'getUser'),
            );
            $complexOutput = [
                'user' => [
                    'id' => 123,
                    'profile' => [
                        'name' => 'John Doe',
                        'preferences' => [
                            'theme' => 'dark',
                            'notifications' => ['email' => true, 'sms' => false],
                        ],
                    ],
                    'roles' => ['admin', 'user'],
                ],
            ];
            $scenario = SimulationScenarioData::success('complex', ['id' => 123], $complexOutput);

            // Act
            $response = $extension->buildSimulatedResponse($request, $scenario);

            // Assert
            expect($response->result)->toBe($complexOutput);
        });

        test('buildScenarioListResponse handles function with no scenarios', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0009',
                call: new CallData(function: 'emptyFunction'),
            );
            $function = Mockery::mock(SimulatableInterface::class);
            $function->shouldReceive('getSimulationScenarios')->andReturn([]);
            $function->shouldReceive('getDefaultScenario')->andReturn('default');

            // Act
            $response = $extension->buildScenarioListResponse($request, $function);

            // Assert
            $ext = $response->extensions[0];
            expect($ext->data['available_scenarios'])->toBe([]);
        });

        test('buildScenarioListResponse handles scenarios without descriptions', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0010',
                call: new CallData(function: 'getUser'),
            );
            $scenarios = [
                new SimulationScenarioData('no_description', ['id' => 1], ['user' => 'test']),
            ];
            $function = Mockery::mock(SimulatableInterface::class);
            $function->shouldReceive('getSimulationScenarios')->andReturn($scenarios);
            $function->shouldReceive('getDefaultScenario')->andReturn('no_description');

            // Act
            $response = $extension->buildScenarioListResponse($request, $function);

            // Assert
            $ext = $response->extensions[0];
            $availableScenarios = $ext->data['available_scenarios'];
            expect($availableScenarios[0]['description'])->toBeNull();
        });

        test('buildUnsupportedResponse handles function names with special characters', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0011',
                call: new CallData(function: 'user:get-profile'),
            );

            // Act
            $response = $extension->buildUnsupportedResponse($request, 'user:get-profile');

            // Assert
            expect($response->errors[0]->message)->toBe("Function 'user:get-profile' does not support simulation");
        });

        test('buildScenarioNotFoundResponse handles scenario names with special characters', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0012',
                call: new CallData(function: 'getUser'),
            );

            // Act
            $response = $extension->buildScenarioNotFoundResponse($request, 'user-not-found-404');

            // Assert
            expect($response->errors[0]->message)->toBe("Simulation scenario 'user-not-found-404' not found");

            $ext = $response->extensions[0];
            expect($ext->data['requested_scenario'])->toBe('user-not-found-404');
        });

        test('buildSimulatedErrorResponse handles error without data field', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0013',
                call: new CallData(function: 'getUser'),
            );
            $scenario = SimulationScenarioData::error(
                'simple_error',
                ['id' => 1],
                'INTERNAL_ERROR',
                'Simple error message',
            );

            // Act
            $response = $extension->buildSimulatedErrorResponse($request, $scenario);

            // Assert
            expect($response->errors[0]->code)->toBe('INTERNAL_ERROR')
                ->and($response->errors[0]->message)->toBe('Simple error message')
                ->and($response->errors[0]->details)->toBeNull();
        });
    });

    describe('Sad Paths', function (): void {
        test('buildSimulatedResponse preserves request ID in response', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'custom-request-id-12345',
                call: new CallData(function: 'test'),
            );
            $scenario = SimulationScenarioData::success('test', ['id' => 1], ['result' => 'ok']);

            // Act
            $response = $extension->buildSimulatedResponse($request, $scenario);

            // Assert
            expect($response->id)->toBe('custom-request-id-12345');
        });

        test('buildSimulatedErrorResponse preserves request ID in error response', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'error-request-id-999',
                call: new CallData(function: 'test'),
            );
            $scenario = SimulationScenarioData::error('error', ['id' => 1], 'ERROR', 'Error');

            // Act
            $response = $extension->buildSimulatedErrorResponse($request, $scenario);

            // Assert
            expect($response->id)->toBe('error-request-id-999');
        });

        test('buildScenarioListResponse preserves request ID', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'list-request-id-456',
                call: new CallData(function: 'test'),
            );
            $function = Mockery::mock(SimulatableInterface::class);
            $function->shouldReceive('getSimulationScenarios')->andReturn([]);
            $function->shouldReceive('getDefaultScenario')->andReturn('default');

            // Act
            $response = $extension->buildScenarioListResponse($request, $function);

            // Assert
            expect($response->id)->toBe('list-request-id-456');
        });

        test('buildUnsupportedResponse preserves request ID', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'unsupported-request-id-789',
                call: new CallData(function: 'test'),
            );

            // Act
            $response = $extension->buildUnsupportedResponse($request, 'test');

            // Assert
            expect($response->id)->toBe('unsupported-request-id-789');
        });

        test('buildScenarioNotFoundResponse preserves request ID', function (): void {
            // Arrange
            $extension = new SimulationExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'not-found-request-id-321',
                call: new CallData(function: 'test'),
            );

            // Act
            $response = $extension->buildScenarioNotFoundResponse($request, 'missing');

            // Assert
            expect($response->id)->toBe('not-found-request-id-321');
        });

        test('isEnabled strictly checks for boolean true', function (): void {
            // Arrange
            $extension = new SimulationExtension();

            // Act & Assert - truthy values that are not boolean true
            expect($extension->isEnabled(['enabled' => 1]))->toBeFalse();
            expect($extension->isEnabled(['enabled' => '1']))->toBeFalse();
            expect($extension->isEnabled(['enabled' => 'true']))->toBeFalse();
            expect($extension->isEnabled(['enabled' => []]))->toBeFalse();
        });

        test('shouldListScenarios strictly checks for boolean true', function (): void {
            // Arrange
            $extension = new SimulationExtension();

            // Act & Assert - truthy values that are not boolean true
            expect($extension->shouldListScenarios(['list_scenarios' => 1]))->toBeFalse();
            expect($extension->shouldListScenarios(['list_scenarios' => '1']))->toBeFalse();
            expect($extension->shouldListScenarios(['list_scenarios' => 'true']))->toBeFalse();
            expect($extension->shouldListScenarios(['list_scenarios' => []]))->toBeFalse();
        });
    });
});

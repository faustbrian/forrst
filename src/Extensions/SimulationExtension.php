<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions;

use Cline\Forrst\Contracts\FunctionInterface;
use Cline\Forrst\Contracts\SimulatableInterface;
use Cline\Forrst\Data\ErrorData;
use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Discovery\SimulationScenarioData;
use Cline\Forrst\Enums\ErrorCode;
use Override;

use function is_string;
use function sprintf;

/**
 * Simulation extension handler.
 *
 * Enables sandbox/demo mode where functions return predefined responses without
 * executing real logic or causing side effects. Unlike dry-run (which validates
 * real requests), simulation operates on predefined input/output scenarios.
 *
 * Use cases:
 * - Interactive API documentation and explorers
 * - Client SDK testing without backend dependencies
 * - Demo environments with predictable behavior
 * - Integration testing with deterministic responses
 *
 * Request options:
 * - enabled: boolean to enable simulation mode
 * - scenario: string name of the scenario to execute (optional, uses default)
 * - list_scenarios: boolean to list available scenarios instead of executing
 *
 * Response data:
 * - simulated: boolean indicating response was simulated
 * - scenario: string name of the scenario that was executed
 * - available_scenarios: array of scenario names (when list_scenarios=true)
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/specs/forrst/extensions/simulation
 */
final class SimulationExtension extends AbstractExtension
{
    #[Override()]
    public function getUrn(): string
    {
        return ExtensionUrn::Simulation->value;
    }

    #[Override()]
    public function isErrorFatal(): bool
    {
        return true;
    }

    /**
     * Check if simulation mode is enabled.
     *
     * @param null|array<string, mixed> $options Extension options from request
     *
     * @return bool True if simulation mode is enabled
     */
    public function isEnabled(?array $options): bool
    {
        return ($options['enabled'] ?? false) === true;
    }

    /**
     * Check if scenario listing is requested.
     *
     * When true, returns available scenarios instead of executing one.
     *
     * @param null|array<string, mixed> $options Extension options from request
     *
     * @return bool True if scenarios should be listed
     */
    public function shouldListScenarios(?array $options): bool
    {
        return ($options['list_scenarios'] ?? false) === true;
    }

    /**
     * Get the requested scenario name.
     *
     * @param null|array<string, mixed> $options Extension options from request
     *
     * @return null|string Scenario name or null for default
     */
    public function getRequestedScenario(?array $options): ?string
    {
        $scenario = $options['scenario'] ?? null;

        return is_string($scenario) ? $scenario : null;
    }

    /**
     * Check if a function supports simulation.
     *
     * @param FunctionInterface $function The function to check
     *
     * @return bool True if function implements SimulatableInterface
     */
    public function supportsSimulation(FunctionInterface $function): bool
    {
        return $function instanceof SimulatableInterface;
    }

    /**
     * Find a scenario by name.
     *
     * @param SimulatableInterface $function Function with scenarios
     * @param string               $name     Scenario name to find
     *
     * @return null|SimulationScenarioData Scenario or null if not found
     */
    public function findScenario(SimulatableInterface $function, string $name): ?SimulationScenarioData
    {
        foreach ($function->getSimulationScenarios() as $scenario) {
            if ($scenario->name === $name) {
                return $scenario;
            }
        }

        return null;
    }

    /**
     * Get the scenario to execute.
     *
     * Returns the requested scenario or the default scenario if none specified.
     *
     * @param SimulatableInterface      $function Function with scenarios
     * @param null|array<string, mixed> $options  Extension options from request
     *
     * @return null|SimulationScenarioData Scenario to execute or null if not found
     */
    public function getScenarioToExecute(SimulatableInterface $function, ?array $options): ?SimulationScenarioData
    {
        $requestedName = $this->getRequestedScenario($options);
        $scenarioName = $requestedName ?? $function->getDefaultScenario();

        return $this->findScenario($function, $scenarioName);
    }

    /**
     * Build a simulated success response.
     *
     * @param RequestObjectData      $request  Original request
     * @param SimulationScenarioData $scenario Scenario being executed
     *
     * @return ResponseData Simulated response with result
     */
    public function buildSimulatedResponse(
        RequestObjectData $request,
        SimulationScenarioData $scenario,
    ): ResponseData {
        $extensionData = [
            'simulated' => true,
            'scenario' => $scenario->name,
        ];

        if ($scenario->metadata !== null) {
            $extensionData['metadata'] = $scenario->metadata;
        }

        return ResponseData::success(
            result: $scenario->output,
            id: $request->id,
            extensions: [
                ExtensionData::response(ExtensionUrn::Simulation->value, $extensionData),
            ],
        );
    }

    /**
     * Build a simulated error response.
     *
     * @param RequestObjectData      $request  Original request
     * @param SimulationScenarioData $scenario Scenario being executed
     *
     * @return ResponseData Simulated response with error
     */
    public function buildSimulatedErrorResponse(
        RequestObjectData $request,
        SimulationScenarioData $scenario,
    ): ResponseData {
        $error = $scenario->error ?? ['code' => 'SIMULATED_ERROR', 'message' => 'Simulated error'];

        $extensionData = [
            'simulated' => true,
            'scenario' => $scenario->name,
        ];

        if ($scenario->metadata !== null) {
            $extensionData['metadata'] = $scenario->metadata;
        }

        return ResponseData::error(
            error: ErrorData::from($error),
            id: $request->id,
            extensions: [
                ExtensionData::response(ExtensionUrn::Simulation->value, $extensionData),
            ],
        );
    }

    /**
     * Build a scenario listing response.
     *
     * Returns available scenarios for the function without executing any.
     *
     * @param RequestObjectData    $request  Original request
     * @param SimulatableInterface $function Function with scenarios
     *
     * @return ResponseData Response listing available scenarios
     */
    public function buildScenarioListResponse(
        RequestObjectData $request,
        SimulatableInterface $function,
    ): ResponseData {
        $scenarios = [];

        foreach ($function->getSimulationScenarios() as $scenario) {
            $scenarios[] = [
                'name' => $scenario->name,
                'description' => $scenario->description,
                'is_error' => $scenario->error !== null,
                'is_default' => $scenario->name === $function->getDefaultScenario(),
            ];
        }

        return ResponseData::success(
            result: null,
            id: $request->id,
            extensions: [
                ExtensionData::response(ExtensionUrn::Simulation->value, [
                    'simulated' => false,
                    'available_scenarios' => $scenarios,
                ]),
            ],
        );
    }

    /**
     * Build an unsupported response.
     *
     * Returns error when function doesn't implement SimulatableInterface.
     *
     * @param RequestObjectData $request      Original request
     * @param string            $functionName Name of the function
     *
     * @return ResponseData Error response indicating simulation not supported
     */
    public function buildUnsupportedResponse(
        RequestObjectData $request,
        string $functionName,
    ): ResponseData {
        return ResponseData::error(
            error: new ErrorData(
                code: ErrorCode::SimulationNotSupported,
                message: sprintf("Function '%s' does not support simulation", $functionName),
            ),
            id: $request->id,
            extensions: [
                ExtensionData::response(ExtensionUrn::Simulation->value, [
                    'simulated' => false,
                    'error' => 'unsupported',
                ]),
            ],
        );
    }

    /**
     * Build a scenario not found response.
     *
     * @param RequestObjectData $request      Original request
     * @param string            $scenarioName Name of the requested scenario
     *
     * @return ResponseData Error response indicating scenario not found
     */
    public function buildScenarioNotFoundResponse(
        RequestObjectData $request,
        string $scenarioName,
    ): ResponseData {
        return ResponseData::error(
            error: new ErrorData(
                code: ErrorCode::SimulationScenarioNotFound,
                message: sprintf("Simulation scenario '%s' not found", $scenarioName),
            ),
            id: $request->id,
            extensions: [
                ExtensionData::response(ExtensionUrn::Simulation->value, [
                    'simulated' => false,
                    'error' => 'scenario_not_found',
                    'requested_scenario' => $scenarioName,
                ]),
            ],
        );
    }
}

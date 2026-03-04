<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Contracts;

use Cline\Forrst\Discovery\SimulationScenarioData;
use Cline\Forrst\Exceptions\InvalidConfigurationException;

/**
 * Forrst simulatable function contract interface.
 *
 * Defines the contract for functions that support simulation mode, allowing
 * clients to invoke functions with predefined scenarios that return known
 * responses without executing real logic or causing side effects.
 *
 * Functions implementing this interface expose simulation scenarios that can
 * be used for:
 * - Interactive API documentation and explorers
 * - Client SDK testing without backend dependencies
 * - Demo environments with predictable behavior
 * - Integration testing with deterministic responses
 *
 * Unlike dry-run (which validates real requests), simulation operates entirely
 * on predefined input/output pairs defined by the function author.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/simulation Simulation extension specification
 */
interface SimulatableInterface extends FunctionInterface
{
    /**
     * Get available simulation scenarios.
     *
     * Returns an array of predefined scenarios that clients can trigger in
     * simulation mode. Each scenario defines specific input arguments and
     * the corresponding output (result or error) that will be returned.
     *
     * Scenarios should cover common use cases:
     * - Success cases with typical data
     * - Error cases (validation errors, not found, permission denied)
     * - Edge cases (empty results, maximum values, special characters)
     *
     * ```php
     * public function getSimulationScenarios(): array
     * {
     *     return [
     *         SimulationScenarioData::success(
     *             name: 'default',
     *             input: ['id' => 'user_123'],
     *             output: ['id' => 'user_123', 'name' => 'Jane Doe'],
     *             description: 'Returns a typical user object',
     *         ),
     *         SimulationScenarioData::error(
     *             name: 'not_found',
     *             input: ['id' => 'user_nonexistent'],
     *             code: 'NOT_FOUND',
     *             message: 'User not found',
     *             description: 'User ID does not exist',
     *         ),
     *     ];
     * }
     * ```
     *
     * @return array<int, SimulationScenarioData> Available simulation scenarios
     */
    public function getSimulationScenarios(): array;

    /**
     * Get the default scenario name.
     *
     * Returns the name of the scenario to use when a client requests simulation
     * mode without specifying a scenario name. Should typically be the most
     * common success case.
     *
     * MUST return a name that exists in getSimulationScenarios().
     *
     * @throws InvalidConfigurationException If default scenario doesn't exist
     * @return string                        Default scenario name (e.g., "default", "success")
     */
    public function getDefaultScenario(): string;

    /**
     * Validate simulation configuration.
     *
     * Validates that the default scenario exists in the available simulation
     * scenarios. This method should be called during function initialization
     * to ensure the simulation configuration is valid.
     *
     * Implementation example:
     * ```php
     * public function validateSimulation(): void
     * {
     *     $scenarios = $this->getSimulationScenarios();
     *     $default = $this->getDefaultScenario();
     *
     *     $scenarioNames = array_map(fn($s) => $s->name, $scenarios);
     *
     *     if (!in_array($default, $scenarioNames, true)) {
     *         throw InvalidConfigurationException::forKey(
     *             'simulation.default_scenario',
     *             sprintf(
     *                 'Default scenario "%s" does not exist. Available: %s',
     *                 $default,
     *                 implode(', ', $scenarioNames)
     *             )
     *         );
     *     }
     * }
     * ```
     *
     * @throws InvalidConfigurationException If default scenario doesn't exist in available scenarios
     */
    public function validateSimulation(): void;
}

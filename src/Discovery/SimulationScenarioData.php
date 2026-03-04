<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Discovery;

use Cline\Forrst\Exceptions\EmptyArrayException;
use Cline\Forrst\Exceptions\EmptyFieldException;
use Cline\Forrst\Exceptions\InvalidFieldValueException;
use Cline\Forrst\Exceptions\MissingRequiredFieldException;
use Spatie\LaravelData\Data;

use function array_diff;
use function array_keys;
use function implode;
use function is_string;
use function mb_trim;
use function preg_match;

/**
 * Predefined simulation scenario for sandbox/demo mode.
 *
 * Defines a complete input/output pair that functions can expose for simulation
 * purposes. Unlike examples (which are documentation), scenarios are executable
 * and allow clients to invoke functions in a sandboxed environment without
 * affecting real data or triggering actual side effects.
 *
 * Use cases:
 * - API explorers and interactive documentation
 * - Client SDK testing without backend dependencies
 * - Demo environments with predictable behavior
 * - Integration testing with known responses
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/specs/forrst/extensions/simulation
 */
final class SimulationScenarioData extends Data
{
    /**
     * Create a new simulation scenario.
     *
     * @param string                    $name        Unique identifier for this scenario within the function
     *                                               (e.g., "success", "not_found", "validation_error").
     *                                               Clients reference this name when requesting simulation.
     * @param array<string, mixed>      $input       The arguments that trigger this scenario. When a client
     *                                               sends these exact arguments in simulation mode, this
     *                                               scenario's output is returned. Partial matching may be
     *                                               supported via the extension's matching strategy.
     * @param mixed                     $output      The response returned when this scenario is triggered.
     *                                               For success scenarios, this is the result value. For
     *                                               error scenarios, use the error parameter instead.
     * @param null|string               $description Human-readable explanation of what this scenario
     *                                               demonstrates (e.g., "User successfully created",
     *                                               "Returns 404 when user ID doesn't exist").
     * @param null|array<string, mixed> $error       Error response for error scenarios. Contains code,
     *                                               message, and optional data. Mutually exclusive with
     *                                               output for error scenarios.
     * @param null|array<string, mixed> $metadata    Optional metadata about the scenario such as
     *                                               response timing simulation, headers, or extension
     *                                               data that should be included in the response.
     */
    public function __construct(
        public readonly string $name,
        public readonly array $input,
        public readonly mixed $output = null,
        public readonly ?string $description = null,
        public readonly ?array $error = null,
        public readonly ?array $metadata = null,
    ) {
        // Validate scenario name
        if (mb_trim($name) === '') {
            throw EmptyFieldException::forField('name');
        }

        // Validate input array is non-empty
        if ($input === []) {
            throw EmptyArrayException::forField('input');
        }

        // Validate mutually exclusive output/error fields
        if ($output !== null && $error !== null) {
            throw InvalidFieldValueException::forField(
                'output/error',
                'Cannot specify both "output" and "error". Success scenarios use output, error scenarios use error field.',
            );
        }

        // Validate error structure if provided
        if ($error === null) {
            return;
        }

        $this->validateErrorStructure($error);
    }

    /**
     * Create a success scenario.
     *
     * Factory method for scenarios that return successful results.
     *
     * @param string               $name        Scenario identifier
     * @param array<string, mixed> $input       Arguments that trigger this scenario
     * @param mixed                $output      Successful result value
     * @param null|string          $description Optional description
     */
    public static function success(
        string $name,
        array $input,
        mixed $output,
        ?string $description = null,
    ): self {
        return new self(
            name: $name,
            input: $input,
            output: $output,
            description: $description,
        );
    }

    /**
     * Create an error scenario.
     *
     * Factory method for scenarios that return error responses.
     *
     * @param string               $name        Scenario identifier
     * @param array<string, mixed> $input       Arguments that trigger this scenario
     * @param string               $code        Error code (SCREAMING_SNAKE_CASE)
     * @param string               $message     Error message
     * @param null|mixed           $data        Optional error data
     * @param null|string          $description Optional description
     */
    public static function error(
        string $name,
        array $input,
        string $code,
        string $message,
        mixed $data = null,
        ?string $description = null,
    ): self {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($data !== null) {
            $error['details'] = $data;
        }

        return new self(
            name: $name,
            input: $input,
            description: $description,
            error: $error,
        );
    }

    /**
     * Validate error array structure.
     *
     * @param array<string, mixed> $error
     *
     * @throws InvalidFieldValueException
     * @throws MissingRequiredFieldException
     */
    private function validateErrorStructure(array $error): void
    {
        $requiredFields = ['code', 'message'];
        $missingFields = array_diff($requiredFields, array_keys($error));

        if ($missingFields !== []) {
            throw MissingRequiredFieldException::forField(implode(', ', $missingFields));
        }

        if (!is_string($error['code']) || !preg_match('/^[A-Z][A-Z0-9_]*$/', $error['code'])) {
            throw InvalidFieldValueException::forField(
                'error.code',
                'Error code must be SCREAMING_SNAKE_CASE string',
            );
        }

        if (!is_string($error['message']) || mb_trim($error['message']) === '') {
            throw EmptyFieldException::forField('error.message');
        }
    }
}

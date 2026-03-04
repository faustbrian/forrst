<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Discovery;

use BackedEnum;
use Cline\Forrst\Exceptions\InvalidFieldTypeException;
use Cline\Forrst\Exceptions\InvalidFieldValueException;
use Cline\Forrst\Exceptions\MissingRequiredFieldException;
use Spatie\LaravelData\Data;

use const E_USER_WARNING;
use const ENT_QUOTES;

use function array_map;
use function count;
use function htmlspecialchars;
use function implode;
use function in_array;
use function is_array;
use function preg_match;
use function preg_match_all;
use function preg_replace_callback;
use function range;
use function sort;
use function sprintf;
use function trigger_error;

/**
 * Error definition for function error documentation.
 *
 * Describes a specific error condition that a function may return. Used in
 * discovery documents to document expected error responses, enabling clients
 * to implement proper error handling and display meaningful error messages.
 *
 * SECURITY CONSIDERATIONS:
 * - Error messages may contain placeholders ({0}, {1}) for dynamic values
 * - ALWAYS escape values before substituting into messages displayed in HTML
 * - Use formatMessage() helper for safe HTML substitution with automatic escaping
 * - Validate JSON schemas to prevent deeply nested structures causing DoS
 * - Never expose sensitive data (passwords, tokens, keys) in error messages
 *
 * @example Basic error definition with enum code
 *
 * ```php
 * $error = new ErrorDefinitionData(
 *     code: ErrorCode::ResourceNotFound,
 *     message: 'Resource with ID {0} not found',
 *     description: 'The requested resource does not exist in the system',
 * );
 * ```
 *
 * @example Error with JSON Schema details
 *
 * ```php
 * $error = new ErrorDefinitionData(
 *     code: 'VALIDATION_FAILED',
 *     message: 'Input validation failed',
 *     details: [
 *         'type' => 'object',
 *         'properties' => [
 *             'field' => ['type' => 'string', 'description' => 'Field that failed'],
 *             'errors' => [
 *                 'type' => 'array',
 *                 'items' => ['type' => 'string'],
 *                 'description' => 'Validation error messages',
 *             ],
 *         ],
 *         'required' => ['field', 'errors'],
 *     ],
 * );
 * ```
 *
 * @example Safe message formatting
 *
 * ```php
 * $formatted = $error->formatMessage([
 *     $userId,  // Automatically HTML-escaped
 *     $action,  // Automatically HTML-escaped
 * ]);
 * echo $formatted; // Safe to display in HTML
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/errors
 * @see https://docs.cline.sh/specs/forrst/discovery#error-definition-object
 * @see https://json-schema.org/ JSON Schema specification
 */
final class ErrorDefinitionData extends Data
{
    /**
     * Machine-readable error code identifier.
     */
    public readonly string $code;

    /**
     * Create a new error definition.
     *
     * @param BackedEnum|string $code    Machine-readable error code identifier following SCREAMING_SNAKE_CASE
     *                                   convention (e.g., ErrorCode::InvalidArgument, "RESOURCE_NOT_FOUND"). Used by
     *                                   clients to programmatically identify and handle specific error conditions
     *                                   without parsing human-readable messages.
     * @param string            $message Human-readable error message template describing the error condition.
     *                                   Use numbered placeholders {0}, {1}, {2} for dynamic values. DO NOT use
     *                                   named placeholders like {fieldName} as they increase injection risk.
     *                                   Always sanitize/escape values before substitution using formatMessage().
     *
     *                                               Example: "Invalid value {0} for field {1}"
     *
     *                                               WARNING: When substituting values, escape HTML/SQL special characters
     *                                               to prevent injection attacks.
     * @param null|string               $description Optional detailed explanation of when this error occurs, what
     *                                               causes it, and how to resolve it. Provides additional context
     *                                               beyond the brief message for documentation and troubleshooting.
     * @param null|array<string, mixed> $details     JSON Schema definition for the error's details field.
     *                                               Must include a valid 'type' property. Nested schemas are
     *                                               validated recursively with a maximum depth of 10 levels.
     *                                               Specifies the structure and validation rules for
     *                                               additional error metadata, enabling type-safe error
     *                                               handling and validation in client implementations.
     */
    public function __construct(
        BackedEnum|string $code,
        public readonly string $message,
        public readonly ?string $description = null,
        public readonly ?array $details = null,
    ) {
        $this->code = match (true) {
            $code instanceof BackedEnum => (string) $code->value,
            default => $this->validateCode($code),
        };

        $this->validateMessagePlaceholders($message);

        if ($details === null) {
            return;
        }

        $this->validateJsonSchema($details);
    }

    /**
     * Safely substitute placeholder values with HTML escaping.
     *
     * @param array<int, scalar> $values Values to substitute into placeholders
     *
     * @return string Message with escaped values substituted
     */
    public function formatMessage(array $values): string
    {
        $escaped = array_map(
            fn (bool|float|int|string $val): string => htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8'),
            $values,
        );

        return preg_replace_callback(
            '/\{(\d+)\}/',
            fn ($matches) => $escaped[(int) $matches[1]] ?? $matches[0],
            $this->message,
        );
    }

    /**
     * Validate error code follows SCREAMING_SNAKE_CASE convention.
     *
     * @throws InvalidFieldValueException
     */
    private function validateCode(string $code): string
    {
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $code)) {
            throw InvalidFieldValueException::forField(
                'code',
                sprintf("Error code must follow SCREAMING_SNAKE_CASE convention. Got: '%s'", $code),
            );
        }

        return $code;
    }

    /**
     * Validate message uses safe numbered placeholders.
     *
     * @throws InvalidFieldValueException
     */
    private function validateMessagePlaceholders(string $message): void
    {
        // Check for potentially unsafe named placeholders
        if (preg_match('/\{[A-Za-z_]\w*\}/', $message)) {
            trigger_error(
                'Warning: Error message uses named placeholders like {fieldName}. '
                .'Consider using numbered placeholders {0}, {1} to prevent injection.',
                E_USER_WARNING,
            );
        }

        // Validate numbered placeholders are sequential
        preg_match_all('/\{(\d+)\}/', $message, $matches);

        if (!isset($matches[1]) || $matches[1] === []) {
            return;
        }

        $indices = array_map(intval(...), $matches[1]);
        sort($indices);
        $expected = range(0, count($indices) - 1);

        if ($indices !== $expected) {
            throw InvalidFieldValueException::forField(
                'message',
                'Message placeholders must be sequential starting from {0}. '
                .'Found: '.implode(', ', array_map(fn (string $i): string => sprintf('{%s}', $i), $indices)),
            );
        }
    }

    /**
     * Validate details field contains valid JSON Schema.
     *
     * @param array<string, mixed> $details
     * @param int                  $depth   Current nesting depth (for DoS prevention)
     *
     * @throws InvalidFieldTypeException
     * @throws InvalidFieldValueException
     * @throws MissingRequiredFieldException
     */
    private function validateJsonSchema(array $details, int $depth = 0): void
    {
        if ($depth > 10) {
            throw InvalidFieldValueException::forField(
                'details',
                'JSON Schema nesting too deep (max 10 levels)',
            );
        }

        // $ref is a valid alternative to type in JSON Schema
        if (!isset($details['type']) && !isset($details['$ref'])) {
            throw MissingRequiredFieldException::forField('details.type');
        }

        // If using $ref, skip type and property validation (ref points to external schema)
        if (isset($details['$ref'])) {
            return;
        }

        $validTypes = ['object', 'array', 'string', 'number', 'integer', 'boolean', 'null'];

        if (!in_array($details['type'], $validTypes, true)) {
            throw InvalidFieldValueException::forField(
                'details.type',
                sprintf("Invalid JSON Schema type '%s'. ", $details['type'])
                .'Must be one of: '.implode(', ', $validTypes),
            );
        }

        // If type is object, validate properties exist
        if ($details['type'] === 'object' && isset($details['properties'])) {
            if (!is_array($details['properties'])) {
                throw InvalidFieldTypeException::forField(
                    'details.properties',
                    'array',
                    $details['properties'],
                );
            }

            // Recursively validate nested schemas
            foreach ($details['properties'] as $propName => $propSchema) {
                if (!is_array($propSchema) || !isset($propSchema['type'])) {
                    throw InvalidFieldValueException::forField(
                        'details.properties.'.$propName,
                        "Property must have a valid JSON Schema with 'type'",
                    );
                }

                $this->validateJsonSchema($propSchema, $depth + 1);
            }
        }

        // If type is array, validate items exist
        if ($details['type'] !== 'array' || !isset($details['items'])) {
            return;
        }

        if (!is_array($details['items']) || !isset($details['items']['type'])) {
            throw InvalidFieldValueException::forField(
                'details.items',
                'JSON Schema "items" must be a valid schema with "type"',
            );
        }

        $this->validateJsonSchema($details['items'], $depth + 1);
    }
}

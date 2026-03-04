<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Discovery;

use Cline\Forrst\Exceptions\InvalidFieldValueException;
use Cline\Forrst\Exceptions\InvalidResultSchemaException;
use Cline\Forrst\Exceptions\MissingRequiredFieldException;
use Spatie\LaravelData\Data;

use function in_array;
use function preg_match;
use function sprintf;

/**
 * RPC method return value type definition.
 *
 * Describes the structure and type of data returned by an RPC method, including
 * whether the result is a resource object, a custom schema, or a collection.
 * Used for documentation, client code generation, and response validation.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/document-structure Document structure documentation
 * @see https://docs.cline.sh/specs/forrst/discovery#result-object Result object specification
 */
final class ResultDescriptorData extends Data
{
    /**
     * Create a new result descriptor.
     *
     * @param null|string               $resource    The resource type name if this method returns resource objects. Identifies
     *                                               which resource type is returned, enabling clients to understand the response
     *                                               structure and relationships. Mutually exclusive with $schema; one must be null.
     * @param null|array<string, mixed> $schema      JSON Schema definition for non-resource responses. Describes the structure,
     *                                               types, and validation rules for custom return values that don't follow the
     *                                               resource object pattern. Mutually exclusive with $resource; one must be null.
     * @param bool                      $collection  Whether the method returns a collection of values. When true, the response
     *                                               contains multiple items in an array. When false, a single value is returned.
     *                                               Applies to both resource and schema-based responses.
     * @param null|string               $description Human-readable description of the return value. Explains what the method
     *                                               returns, when different response types might be returned, and any important
     *                                               behavioral notes about the result structure or content.
     */
    public function __construct(
        public readonly ?string $resource = null,
        public readonly ?array $schema = null,
        public readonly bool $collection = false,
        public readonly ?string $description = null,
    ) {
        // Validate mutually exclusive fields
        if ($this->resource !== null && $this->schema !== null) {
            throw InvalidFieldValueException::forField(
                'resource/schema',
                'Cannot specify both "resource" and "schema". Use resource for resource objects '.
                'or schema for custom return types, but not both.',
            );
        }

        // At least one must be specified
        if ($this->resource === null && $this->schema === null) {
            throw MissingRequiredFieldException::forField('resource or schema');
        }

        // Validate resource name format if provided
        if ($this->resource !== null && !preg_match('/^[a-z][a-z0-9_]*$/', $this->resource)) {
            throw InvalidFieldValueException::forField(
                'resource',
                sprintf("Invalid resource name: '%s'. Must be snake_case lowercase (e.g., 'user', 'order_item')", $this->resource),
            );
        }

        // Validate JSON Schema structure if provided
        if ($this->schema === null) {
            return;
        }

        $this->validateJsonSchema($this->schema);
    }

    /**
     * Validate JSON Schema structure.
     *
     * @param  array<string, mixed>         $schema
     * @throws InvalidResultSchemaException
     */
    private function validateJsonSchema(array $schema): void
    {
        if (!isset($schema['type']) && !isset($schema['$ref'])) {
            throw InvalidResultSchemaException::forSchema(
                'JSON Schema must include "type" or "$ref" property',
            );
        }

        if (!isset($schema['type'])) {
            return;
        }

        $validTypes = ['null', 'boolean', 'object', 'array', 'number', 'string', 'integer'];

        if (!in_array($schema['type'], $validTypes, true)) {
            throw InvalidResultSchemaException::forSchema(
                sprintf("Invalid JSON Schema type: '%s'", $schema['type']),
            );
        }
    }
}

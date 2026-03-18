<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\JsonSchema;

use Cline\Struct\AbstractData;
use Cline\Struct\Metadata\MetadataFactory;
use Cline\Struct\Validation\RuleInferrer;
use Error;

use function array_merge;
use function array_values;
use function class_exists;
use function explode;
use function is_a;
use function is_object;
use function is_string;
use function resolve;
use function sprintf;

/**
 * Transforms complete validation rule sets into JSON Schema documents.
 *
 * Orchestrates the conversion of Laravel validation rule arrays into complete
 * JSON Schema objects with properties, required fields, and type definitions.
 * Provides a high-level interface for schema generation from validation rules,
 * enabling automatic API documentation and client-side validation.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/
 */
final class RulesTransformer
{
    /**
     * Transform Laravel validation rules into a complete JSON Schema object.
     *
     * Processes all field rules and generates a complete JSON Schema document
     * with type, properties, and required fields. Delegates individual field
     * processing to RuleTransformer, then merges additional property schemas
     * when provided for enhanced schema customization.
     *
     * ```php
     * $schema = RulesTransformer::transform([
     *     'email' => 'required|email|max:255',
     *     'name' => 'required|string|min:3'
     * ]);
     * // Returns: ['type' => 'object', 'properties' => [...], 'required' => ['email', 'name']]
     * ```
     *
     * @param array<string, array<int, object|string>|string> $rules      Laravel validation rules keyed by field name,
     *                                                                    supporting both pipe-delimited strings and arrays
     * @param array<string, array<string, mixed>>             $properties Additional schema properties to merge for each field,
     *                                                                    allowing custom extensions beyond standard validation
     *
     * @return array<string, mixed> Complete JSON Schema object with type, properties, and required fields
     */
    public static function transform(array $rules, array $properties = []): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];

        foreach ($rules as $field => $fieldRules) {
            $parsedRules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            $fieldSchema = RuleTransformer::transform($field, $parsedRules);

            if ($fieldSchema === []) {
                continue;
            }

            if (!empty($fieldSchema['required'])) {
                $schema['required'][] = $field;

                if ($fieldSchema['type'] !== 'object') {
                    unset($fieldSchema['required']);
                }
            }

            $schema['properties'][$field] = $fieldSchema;
        }

        foreach ($properties as $field => $fieldSchema) {
            $schema['properties'][$field] = array_merge($schema['properties'][$field] ?? [], $fieldSchema);
        }

        return $schema;
    }

    /**
     * Transform a Spatie Laravel Data object into a JSON Schema object.
     *
     * Convenience method for generating JSON Schema from Laravel Data objects
     * by extracting their validation rules and processing them through the
     * standard transformation pipeline. Useful for automatically generating
     * schemas from typed Data classes.
     *
     * @param class-string<AbstractData>          $data       The Laravel Data class to transform,
     *                                                        must have validation rules defined
     * @param array<string, array<string, mixed>> $properties Additional schema properties to merge
     *                                                        for custom field extensions
     *
     * @return array<string, mixed> Complete JSON Schema object derived from the Data class validation rules
     */
    public static function transformDataObject(string $data, array $properties = []): array
    {
        if (!class_exists($data) || !is_a($data, AbstractData::class, true)) {
            throw new Error(sprintf(
                'Class [%s] must exist and extend [%s].',
                $data,
                AbstractData::class,
            ));
        }

        /** @var MetadataFactory $metadataFactory */
        $metadataFactory = resolve(MetadataFactory::class);
        /** @var RuleInferrer $ruleInferrer */
        $ruleInferrer = resolve(RuleInferrer::class);

        return self::transform(
            self::normalizeRules($ruleInferrer->infer($metadataFactory->for($data))),
            $properties,
        );
    }

    /**
     * @param  array<string, array<int, mixed>> $rules
     * @return array<string, array<int, mixed>>
     */
    private static function normalizeRules(array $rules): array
    {
        $normalized = [];

        foreach ($rules as $field => $fieldRules) {
            $seen = [];

            foreach ($fieldRules as $rule) {
                if ($rule === 'sometimes') {
                    continue;
                }

                $key = is_object($rule) ? spl_object_hash($rule) : (string) $rule;

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $normalized[$field][] = $rule;
            }

            $normalized[$field] = array_values($normalized[$field] ?? []);
        }

        return $normalized;
    }
}

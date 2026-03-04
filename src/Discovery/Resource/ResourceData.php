<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Discovery\Resource;

use Spatie\LaravelData\Data;

/**
 * Complete resource type definition with attributes, relationships, and capabilities.
 *
 * Represents the schema and metadata for a resource type in the API, including all
 * attributes, their capabilities, relationships to other resources, and resource-level
 * metadata. Serves as the definitive description of a resource's structure and behavior.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/resource-objects Resource objects documentation
 * @see https://docs.cline.sh/specs/forrst/discovery#resource-object Resource object specification
 */
final class ResourceData extends Data
{
    /**
     * Create a new resource definition.
     *
     * @param string                                         $type          The resource type identifier. Uniquely identifies this resource
     *                                                                      type across the API. Used in resource objects, relationship linkage,
     *                                                                      and type-based filtering. Should be plural and lowercase by convention.
     * @param array<string, AttributeData>                   $attributes    Map of attribute names to their definitions. Each entry describes
     *                                                                      an attribute's schema, capabilities, and metadata. Keys are the
     *                                                                      attribute names as they appear in resource objects.
     * @param null|string                                    $description   Human-readable description of this resource type. Explains what
     *                                                                      the resource represents in the domain model and when it should be
     *                                                                      used. Provides context for API consumers building integrations.
     * @param null|array<string, RelationshipDefinitionData> $relationships Map of relationship names to their definitions. Each entry describes
     *                                                                      a relationship to another resource type including cardinality and
     *                                                                      capabilities. Keys are relationship names as used in resource objects.
     * @param null|array<string, mixed>                      $meta          JSON Schema definition for resource-level metadata. Describes the
     *                                                                      structure and validation rules for the meta object that can appear
     *                                                                      on resource objects of this type. Null indicates no metadata support.
     */
    public function __construct(
        public readonly string $type,
        public readonly array $attributes,
        public readonly ?string $description = null,
        public readonly ?array $relationships = null,
        public readonly ?array $meta = null,
    ) {}
}

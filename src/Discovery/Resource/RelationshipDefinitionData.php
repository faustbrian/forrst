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
 * Resource relationship metadata and capabilities definition.
 *
 * Describes a relationship between resources including the related resource type,
 * cardinality (one-to-one, one-to-many), whether it can be filtered or included,
 * and which nested relationships can be traversed through this relationship.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/resource-objects Resource objects documentation
 * @see https://docs.cline.sh/specs/forrst/discovery#relationship-definition-object Relationship definition specification
 */
final class RelationshipDefinitionData extends Data
{
    /**
     * Create a new relationship definition.
     *
     * @param string                  $resource    The type name of the related resource. Identifies which resource type
     *                                             this relationship points to, enabling clients to understand the data
     *                                             model and construct appropriate requests for related data.
     * @param string                  $cardinality The relationship cardinality, indicating whether this is a singular
     *                                             relationship (one-to-one) or a collection (one-to-many). Common values
     *                                             are "one" and "many". Informs clients about the structure of related data.
     * @param null|string             $description Human-readable description of the relationship's purpose and usage.
     *                                             Explains the business meaning of the relationship and when related
     *                                             resources should be loaded or displayed.
     * @param bool                    $filterable  Whether the parent resource can be filtered based on this relationship.
     *                                             When true, clients can filter resources by properties of related resources,
     *                                             enabling queries like "posts where author.name equals 'John'". Defaults to false.
     * @param bool                    $includable  Whether this relationship can be included in responses via the include
     *                                             query parameter. When true, clients can request related resources be loaded
     *                                             and embedded in the response. Defaults to true for convenience.
     * @param null|array<int, string> $nested      List of nested relationship names that can be traversed through this
     *                                             relationship. For example, if this is an "author" relationship, nested
     *                                             might include ["posts", "comments"] to allow "author.posts" includes.
     */
    public function __construct(
        public readonly string $resource,
        public readonly string $cardinality,
        public readonly ?string $description = null,
        public readonly bool $filterable = false,
        public readonly bool $includable = true,
        public readonly ?array $nested = null,
    ) {}
}

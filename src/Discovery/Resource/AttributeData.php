<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Discovery\Resource;

use Cline\Forrst\Discovery\DeprecatedData;
use Spatie\LaravelData\Data;

/**
 * Resource attribute metadata and capabilities definition.
 *
 * Describes a single attribute of a resource including its data type schema,
 * whether it can be used for filtering or sorting, supported filter operators,
 * sparse fieldset eligibility, and deprecation status.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/resource-objects Resource objects documentation
 * @see https://docs.cline.sh/specs/forrst/discovery#attribute-object Attribute object specification
 */
final class AttributeData extends Data
{
    /**
     * Create a new resource attribute definition.
     *
     * @param array<string, mixed>    $schema          JSON Schema definition describing the attribute's data type,
     *                                                 format, validation rules, and constraints. Follows JSON Schema
     *                                                 specification to enable client-side validation and type checking.
     * @param null|string             $description     Human-readable description of the attribute's purpose and usage.
     *                                                 Provides context for API consumers about what the attribute
     *                                                 represents and when it is populated or relevant.
     * @param bool                    $filterable      Whether this attribute can be used in filter expressions. When true,
     *                                                 clients can filter resources based on this attribute's value using
     *                                                 query parameters. Defaults to false for security and performance.
     * @param null|array<int, string> $filterOperators List of filter operators supported for this attribute when filterable.
     *                                                 Common operators include "eq", "ne", "gt", "lt", "in", "like". When
     *                                                 null and filterable is true, all standard operators are supported.
     * @param bool                    $sortable        Whether this attribute can be used for sorting result sets. When true,
     *                                                 clients can request results ordered by this attribute in ascending or
     *                                                 descending order. Defaults to false for performance considerations.
     * @param bool                    $sparse          Whether this attribute can be excluded via sparse fieldset requests.
     *                                                 When true, clients can omit this attribute from responses to reduce
     *                                                 payload size. Defaults to true; set to false for required fields.
     * @param null|DeprecatedData     $deprecated      Deprecation metadata if this attribute is deprecated. Includes version
     *                                                 information, removal timeline, and migration guidance. Null indicates
     *                                                 the attribute is currently supported and not planned for removal.
     */
    public function __construct(
        public readonly array $schema,
        public readonly ?string $description = null,
        public readonly bool $filterable = false,
        public readonly ?array $filterOperators = null,
        public readonly bool $sortable = false,
        public readonly bool $sparse = true,
        public readonly ?DeprecatedData $deprecated = null,
    ) {}
}

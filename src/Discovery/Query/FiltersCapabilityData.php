<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Discovery\Query;

use Spatie\LaravelData\Data;

/**
 * Filtering capability configuration for query operations.
 *
 * Defines the filtering capabilities available for a query endpoint, including
 * whether filtering is enabled, support for boolean logic in filter expressions,
 * and which resource types can be filtered.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/query Query extension documentation
 * @see https://docs.cline.sh/specs/forrst/discovery#filters-capability Filters capability specification
 */
final class FiltersCapabilityData extends Data
{
    /**
     * Create a new filters capability configuration.
     *
     * @param bool                    $enabled      Whether filtering is enabled for this endpoint. When false,
     *                                              filter query parameters will be rejected or ignored.
     * @param bool                    $booleanLogic Whether complex boolean logic (AND/OR operations) is supported
     *                                              in filter expressions. Enables advanced filtering scenarios
     *                                              with multiple conditions combined using logical operators.
     * @param null|array<int, string> $resources    List of resource type names that support filtering. When null,
     *                                              all resources accessible through this endpoint can be filtered.
     *                                              Use this to restrict filtering to specific resource types.
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly bool $booleanLogic = false,
        public readonly ?array $resources = null,
    ) {}
}

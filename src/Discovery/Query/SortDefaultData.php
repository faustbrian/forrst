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
 * Default sort order configuration for query operations.
 *
 * Specifies the attribute and direction used when a client does not explicitly
 * request sorting. Ensures consistent, predictable ordering of results across
 * API requests and provides a stable default user experience.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/query Query extension documentation
 * @see https://docs.cline.sh/specs/forrst/discovery#sort-default Sort default specification
 */
final class SortDefaultData extends Data
{
    /**
     * Create a new default sort configuration.
     *
     * @param string $attribute The attribute name to sort by when no explicit sort is requested.
     *                          This should be a sortable attribute defined in the resource schema,
     *                          typically an ID, timestamp, or other commonly sorted field that
     *                          provides meaningful ordering for most use cases.
     * @param string $direction The sort direction to apply. Must be either "asc" for ascending order
     *                          or "desc" for descending order. Defaults to "asc" for natural ordering
     *                          where lower values come first (e.g., chronological, alphabetical).
     */
    public function __construct(
        public readonly string $attribute,
        public readonly string $direction = 'asc',
    ) {}
}

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
 * Sorting capability configuration for query operations.
 *
 * Defines whether clients can request sorted results, constraints on the number
 * of sort criteria that can be combined, and the default sort order applied when
 * no explicit sorting is requested.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/query Query extension documentation
 * @see https://docs.cline.sh/specs/forrst/discovery#sorts-capability Sorts capability specification
 */
final class SortsCapabilityData extends Data
{
    /**
     * Create a new sorts capability configuration.
     *
     * @param bool                 $enabled     Whether sorting is enabled for this endpoint. When false, sort query
     *                                          parameters will be rejected or ignored, and results are returned in
     *                                          an undefined or implementation-specific order.
     * @param null|int             $maxSorts    Maximum number of sort criteria that can be combined in a single request.
     *                                          For example, a value of 3 allows sorting by up to three attributes like
     *                                          "name,created_at,id". Prevents performance issues from complex multi-field
     *                                          sorts. Null indicates no limit on the number of sort criteria.
     * @param null|SortDefaultData $defaultSort The default sort order applied when clients do not specify sorting. Ensures
     *                                          consistent, predictable ordering across requests and provides a stable user
     *                                          experience. Null indicates results may be returned in arbitrary order.
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly ?int $maxSorts = null,
        public readonly ?SortDefaultData $defaultSort = null,
    ) {}
}

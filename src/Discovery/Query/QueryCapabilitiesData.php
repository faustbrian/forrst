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
 * Aggregate query capabilities for list and search operations.
 *
 * Combines all query-related capabilities (filtering, sorting, field selection,
 * relationship inclusion, and pagination) into a single configuration object
 * that describes what query operations are supported by an endpoint.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/query Query extension documentation
 * @see https://docs.cline.sh/specs/forrst/discovery#query-capabilities-object Query capabilities specification
 */
final class QueryCapabilitiesData extends Data
{
    /**
     * Create a new query capabilities configuration.
     *
     * @param null|FiltersCapabilityData       $filters       Configuration for filtering capabilities. Defines whether
     *                                                        and how clients can filter result sets based on attribute
     *                                                        values and conditions. Null indicates no filtering support.
     * @param null|SortsCapabilityData         $sorts         Configuration for sorting capabilities. Specifies whether
     *                                                        clients can request sorted results, maximum number of sort
     *                                                        criteria, and default sort order. Null indicates no sorting.
     * @param null|FieldsCapabilityData        $fields        Configuration for sparse fieldset capabilities. Allows clients
     *                                                        to request specific fields in responses to reduce payload size.
     *                                                        Null indicates all fields are always returned.
     * @param null|RelationshipsCapabilityData $relationships Configuration for relationship inclusion capabilities. Controls
     *                                                        whether clients can include related resources in responses and
     *                                                        the depth of relationship traversal. Null indicates no inclusion.
     * @param null|PaginationCapabilityData    $pagination    Configuration for pagination capabilities. Defines supported
     *                                                        pagination styles, default page sizes, and maximum result limits.
     *                                                        Null indicates endpoint returns all results unpaginated.
     */
    public function __construct(
        public readonly ?FiltersCapabilityData $filters = null,
        public readonly ?SortsCapabilityData $sorts = null,
        public readonly ?FieldsCapabilityData $fields = null,
        public readonly ?RelationshipsCapabilityData $relationships = null,
        public readonly ?PaginationCapabilityData $pagination = null,
    ) {}
}

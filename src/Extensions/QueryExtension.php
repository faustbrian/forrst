<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions;

use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\ResponseData;
use Override;

use function min;

/**
 * Query extension handler.
 *
 * Enables rich querying capabilities for resource collections including filtering,
 * sorting, pagination, sparse fieldsets, and relationship inclusion. Provides a
 * standardized query interface similar to JSON:API or GraphQL, allowing clients
 * to precisely specify the data they need while minimizing payload size and
 * reducing over-fetching.
 *
 * Request options:
 * - filters: Filter conditions organized by resource type with operators and values
 * - sorts: Sort directives specifying attributes and direction (asc/desc)
 * - pagination: Pagination parameters supporting offset, cursor, or keyset styles
 * - fields: Sparse fieldsets by resource type to limit returned fields
 * - relationships: Related resources to include in the response (eager loading)
 *
 * Response data:
 * - capabilities: Array of query capabilities that were successfully applied
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/query
 */
final class QueryExtension extends AbstractExtension
{
    /**
     * Query capabilities.
     */
    public const string CAPABILITY_FILTERING = 'filtering';

    public const string CAPABILITY_SORTING = 'sorting';

    public const string CAPABILITY_PAGINATION = 'pagination';

    public const string CAPABILITY_FIELDS = 'fields';

    public const string CAPABILITY_RELATIONSHIPS = 'relationships';

    /**
     * Pagination styles.
     */
    public const string PAGINATION_OFFSET = 'offset';

    public const string PAGINATION_CURSOR = 'cursor';

    public const string PAGINATION_KEYSET = 'keyset';

    /**
     * Sort directions.
     */
    public const string SORT_ASC = 'asc';

    public const string SORT_DESC = 'desc';

    /**
     * Filter boolean operators.
     */
    public const string BOOLEAN_AND = 'and';

    public const string BOOLEAN_OR = 'or';

    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function getUrn(): string
    {
        return ExtensionUrn::Query->value;
    }

    /**
     * {@inheritDoc}
     *
     * Query errors (invalid filters, sorts, etc.) should fail the request.
     */
    #[Override()]
    public function isErrorFatal(): bool
    {
        return true;
    }

    /**
     * Get filters from options.
     *
     * Extracts filter conditions organized by resource type. Each filter specifies
     * an attribute, operator (eq, ne, gt, like, etc.), optional value, and optional
     * boolean combinator (and/or) for complex queries.
     *
     * @param  null|array<string, mixed>                                                                              $options Extension options from request
     * @return array<string, array<int, array{attribute: string, operator: string, value?: mixed, boolean?: string}>> Filters grouped by resource type
     */
    public function getFilters(?array $options): array
    {
        // @phpstan-ignore return.type
        return $options['filters'] ?? [];
    }

    /**
     * Get sorts from options.
     *
     * @param  null|array<string, mixed>                               $options Extension options
     * @return array<int, array{attribute: string, direction: string}> Sort directives
     */
    public function getSorts(?array $options): array
    {
        // @phpstan-ignore return.type
        return $options['sorts'] ?? [];
    }

    /**
     * Get pagination from options.
     *
     * @param  null|array<string, mixed> $options Extension options
     * @return array<string, mixed>      Pagination parameters
     */
    public function getPagination(?array $options): array
    {
        // @phpstan-ignore return.type
        return $options['pagination'] ?? [];
    }

    /**
     * Get fields (sparse fieldsets) from options.
     *
     * @param  null|array<string, mixed>         $options Extension options
     * @return array<string, array<int, string>> Fields by resource
     */
    public function getFields(?array $options): array
    {
        // @phpstan-ignore return.type
        return $options['fields'] ?? [];
    }

    /**
     * Get relationships from options.
     *
     * @param  null|array<string, mixed> $options Extension options
     * @return array<int, string>        Relationship names to include
     */
    public function getRelationships(?array $options): array
    {
        // @phpstan-ignore return.type
        return $options['relationships'] ?? [];
    }

    /**
     * Determine which capabilities were used based on options.
     *
     * @param  null|array<string, mixed> $options Extension options
     * @return array<int, string>        List of capabilities that were used
     */
    public function getUsedCapabilities(?array $options): array
    {
        $capabilities = [];

        if ($this->getFilters($options) !== []) {
            $capabilities[] = self::CAPABILITY_FILTERING;
        }

        if ($this->getSorts($options) !== []) {
            $capabilities[] = self::CAPABILITY_SORTING;
        }

        if ($this->getPagination($options) !== []) {
            $capabilities[] = self::CAPABILITY_PAGINATION;
        }

        if ($this->getFields($options) !== []) {
            $capabilities[] = self::CAPABILITY_FIELDS;
        }

        if ($this->getRelationships($options) !== []) {
            $capabilities[] = self::CAPABILITY_RELATIONSHIPS;
        }

        return $capabilities;
    }

    /**
     * Build offset-based pagination metadata.
     *
     * @param  int                  $limit   Items per page
     * @param  int                  $offset  Current offset
     * @param  null|int             $total   Total items available
     * @param  bool                 $hasMore Whether more items exist
     * @return array<string, mixed> Pagination metadata
     */
    public function buildOffsetPagination(
        int $limit,
        int $offset,
        ?int $total = null,
        bool $hasMore = false,
    ): array {
        $pagination = [
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => $hasMore,
        ];

        if ($total !== null) {
            $pagination['total'] = $total;
        }

        return $pagination;
    }

    /**
     * Build cursor-based pagination metadata.
     *
     * @param  int                  $limit      Items per page
     * @param  null|string          $nextCursor Cursor for next page
     * @param  null|string          $prevCursor Cursor for previous page
     * @param  bool                 $hasMore    Whether more items exist
     * @return array<string, mixed> Pagination metadata
     */
    public function buildCursorPagination(
        int $limit,
        ?string $nextCursor = null,
        ?string $prevCursor = null,
        bool $hasMore = false,
    ): array {
        $pagination = [
            'limit' => $limit,
            'has_more' => $hasMore,
        ];

        if ($nextCursor !== null) {
            $pagination['next_cursor'] = $nextCursor;
        }

        if ($prevCursor !== null) {
            $pagination['prev_cursor'] = $prevCursor;
        }

        return $pagination;
    }

    /**
     * Build keyset-based pagination metadata.
     *
     * @param  int                  $limit    Items per page
     * @param  null|string          $newestId ID of newest item
     * @param  null|string          $oldestId ID of oldest item
     * @param  bool                 $hasMore  Whether more items exist
     * @return array<string, mixed> Pagination metadata
     */
    public function buildKeysetPagination(
        int $limit,
        ?string $newestId = null,
        ?string $oldestId = null,
        bool $hasMore = false,
    ): array {
        $pagination = [
            'limit' => $limit,
            'has_more' => $hasMore,
        ];

        if ($newestId !== null) {
            $pagination['newest_id'] = $newestId;
        }

        if ($oldestId !== null) {
            $pagination['oldest_id'] = $oldestId;
        }

        return $pagination;
    }

    /**
     * Build a sort directive.
     *
     * @param  string                $attribute Attribute to sort by
     * @param  string                $direction Sort direction (asc, desc)
     * @return array<string, string> Sort directive
     */
    public function buildSort(string $attribute, string $direction = self::SORT_ASC): array
    {
        return [
            'attribute' => $attribute,
            'direction' => $direction,
        ];
    }

    /**
     * Build a filter condition.
     *
     * @param  string               $attribute Attribute to filter
     * @param  string               $operator  Filter operator
     * @param  mixed                $value     Filter value
     * @param  string               $boolean   Boolean combinator (and, or)
     * @return array<string, mixed> Filter condition
     */
    public function buildFilter(
        string $attribute,
        string $operator,
        mixed $value = null,
        string $boolean = self::BOOLEAN_AND,
    ): array {
        $filter = [
            'attribute' => $attribute,
            'operator' => $operator,
        ];

        // Null operators (is_null, is_not_null) don't require a value
        if ($value !== null) {
            $filter['value'] = $value;
        }

        if ($boolean !== self::BOOLEAN_AND) {
            $filter['boolean'] = $boolean;
        }

        return $filter;
    }

    /**
     * Enrich a response with query extension data.
     *
     * @param  ResponseData       $response     Original response
     * @param  array<int, string> $capabilities Capabilities that were used
     * @return ResponseData       Enriched response
     */
    public function enrichResponse(ResponseData $response, array $capabilities): ResponseData
    {
        $extensions = $response->extensions ?? [];
        $extensions[] = ExtensionData::response(ExtensionUrn::Query->value, [
            'capabilities' => $capabilities,
        ]);

        return new ResponseData(
            protocol: $response->protocol,
            id: $response->id,
            result: $response->result,
            errors: $response->errors,
            extensions: $extensions,
            meta: $response->meta,
        );
    }

    /**
     * Check if a pagination style is offset-based.
     *
     * @param  array<string, mixed> $pagination Pagination parameters
     * @return bool                 True if offset-based
     */
    public function isOffsetPagination(array $pagination): bool
    {
        return isset($pagination['offset']) || (isset($pagination['limit']) && !isset($pagination['cursor']) && !isset($pagination['after_id']));
    }

    /**
     * Check if a pagination style is cursor-based.
     *
     * @param  array<string, mixed> $pagination Pagination parameters
     * @return bool                 True if cursor-based
     */
    public function isCursorPagination(array $pagination): bool
    {
        return isset($pagination['cursor']);
    }

    /**
     * Check if a pagination style is keyset-based.
     *
     * @param  array<string, mixed> $pagination Pagination parameters
     * @return bool                 True if keyset-based
     */
    public function isKeysetPagination(array $pagination): bool
    {
        return isset($pagination['after_id']) || isset($pagination['before_id']);
    }

    /**
     * Get the pagination limit with a default.
     *
     * @param  array<string, mixed> $pagination Pagination parameters
     * @param  int                  $default    Default limit
     * @param  int                  $max        Maximum allowed limit
     * @return int                  The pagination limit
     */
    public function getPaginationLimit(array $pagination, int $default = 25, int $max = 100): int
    {
        /** @var int $limit */
        $limit = $pagination['limit'] ?? $default;

        return min($limit, $max);
    }
}

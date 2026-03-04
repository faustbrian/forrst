<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\QueryBuilders;

use Cline\Forrst\Exceptions\InvalidFieldsException;
use Cline\Forrst\Exceptions\InvalidFiltersException;
use Cline\Forrst\Exceptions\InvalidRelationshipsException;
use Cline\Forrst\Exceptions\InvalidSortsException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Traits\ForwardsCalls;

use function array_key_exists;
use function array_merge;
use function array_values;
use function assert;
use function class_exists;
use function in_array;
use function is_array;
use function is_string;
use function throw_unless;

/**
 * Validates and constructs Eloquent queries with field selection, filtering, and sorting.
 *
 * Wraps Laravel's Eloquent Builder with security-focused validation, ensuring all
 * requested query operations (fields, filters, relationships, sorts) are authorized
 * via resource-defined allow-lists. Prevents unauthorized data access by rejecting
 * requests for fields or relationships not explicitly permitted by the resource class.
 *
 * Supports complex query patterns including relationship eager loading with constraints,
 * multi-field filtering with boolean operators, and cascading sorts across relationships.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/query
 *
 * @mixin Builder<Model>
 */
final class QueryBuilder
{
    use ForwardsCalls;

    /**
     * Validated field selections organized by resource type.
     *
     * Contains only fields that passed allow-list validation. Keys are resource
     * names ('self' for the primary model), values are arrays of field names.
     *
     * @var array<string, array<int, string>>
     */
    private array $queryFields = [];

    /**
     * Validated filter conditions organized by resource type.
     *
     * Contains filter specifications that passed allow-list validation, including
     * attribute name, comparison operator, filter value, and boolean conjunction.
     *
     * @var array<string, array<int, array{attribute: string, operator: string, value: mixed, boolean?: string}>>
     */
    private array $queryFilters = [];

    /**
     * Validated relationships for eager loading, organized by resource type.
     *
     * Contains relationship names that passed allow-list validation. Keys are
     * resource names, values are arrays of relationship method names.
     *
     * @var array<string, array<int, string>>
     */
    private array $queryRelationships = [];

    /**
     * Validated sort directives organized by resource type.
     *
     * Contains sort specifications that passed allow-list validation, including
     * the attribute to sort by and the sort direction (asc/desc).
     *
     * @var array<string, array<int, array{attribute: string, direction: string}>>
     */
    private array $querySorts = [];

    /**
     * Fully qualified class name of the Eloquent model being queried.
     *
     * @var class-string
     *
     * @phpstan-ignore-next-line property.onlyWritten
     */
    private readonly string $model;

    /**
     * Database table name for the primary model.
     *
     * @phpstan-ignore-next-line property.onlyWritten
     */
    private readonly string $modelTable;

    /**
     * Resource type identifier derived from the model's table name.
     *
     * @phpstan-ignore-next-line property.onlyWritten
     */
    private readonly string $modelType;

    /**
     * Underlying Eloquent Builder instance that executes the final query.
     *
     * @var Builder<Model>
     */
    private readonly Builder $subject;

    /**
     * Creates a query builder with validated parameters and configured Eloquent query.
     *
     * Validates all requested query operations against their corresponding allow-lists,
     * throwing exceptions for unauthorized fields, filters, relationships, or sorts.
     * Immediately constructs the underlying Eloquent query with all validated constraints.
     *
     * @param class-string                                                                                          $resource             Resource class defining model and allow-lists
     * @param array<string, array<int, string>>                                                                     $requestFields        Field selections requested by client, keyed by resource name
     * @param array<string, array<int, string>>                                                                     $allowedFields        Field selections permitted by resource configuration
     * @param array<string, array<int, array{attribute: string, operator: string, value: mixed, boolean?: string}>> $requestFilters       Filter conditions requested by client, keyed by resource name
     * @param array<string, array<int, string>>                                                                     $allowedFilters       Filter attributes permitted by resource configuration
     * @param array<string, array<int, string>>                                                                     $requestRelationships Relationships requested for eager loading, keyed by resource name
     * @param array<string, array<int, string>>                                                                     $allowedRelationships Relationships permitted by resource configuration
     * @param array<string, array<int, array{attribute: string, direction: string}>>                                $requestSorts         Sort directives requested by client, keyed by resource name
     * @param array<string, array<int, string>>                                                                     $allowedSorts         Sort attributes permitted by resource configuration
     *
     * @throws InvalidFieldsException        When any requested field is not in the allow-list
     * @throws InvalidFiltersException       When any filter attribute is not in the allow-list
     * @throws InvalidRelationshipsException When any relationship is not in the allow-list
     * @throws InvalidSortsException         When any sort attribute is not in the allow-list
     */
    private function __construct(
        /**
         * @phpstan-ignore-next-line property.onlyWritten
         */
        private readonly string $resource,
        private readonly array $requestFields,
        private readonly array $allowedFields,
        private readonly array $requestFilters,
        private readonly array $allowedFilters,
        private readonly array $requestRelationships,
        private readonly array $allowedRelationships,
        private readonly array $requestSorts,
        private readonly array $allowedSorts,
    ) {
        $model = $resource::getModel();
        $modelTable = $resource::getModelTable();
        $modelType = $resource::getModelType();

        assert(is_string($model) && class_exists($model));
        assert(is_string($modelTable));
        assert(is_string($modelType));

        /** @var class-string $model */
        $this->model = $model;
        $this->modelTable = $modelTable;
        $this->modelType = $modelType;

        $query = $model::query();
        assert($query instanceof Builder);
        $this->subject = $query;

        $this->collectFields();
        $this->collectFilters();
        $this->collectRelationships();
        $this->collectSorts();

        $this->applyToQuery();
    }

    /**
     * Proxies method calls to the underlying Eloquent builder for fluent chaining.
     *
     * Enables transparent access to all Eloquent Builder methods while maintaining
     * the QueryBuilder wrapper. Returns $this for chainable builder methods to
     * preserve the fluent interface, otherwise returns the actual method result.
     *
     * @param string            $name      Method name to invoke on the Eloquent builder
     * @param array<int, mixed> $arguments Method arguments to forward
     *
     * @return mixed Result from the builder method, or $this for chaining
     */
    public function __call(string $name, array $arguments)
    {
        $result = $this->forwardCallTo($this->subject, $name, $arguments);

        // If the forwarded method call is part of a chain we can return $this
        // instead of the actual $result to keep the chain going.
        if ($result === $this->subject) {
            return $this;
        }

        return $result;
    }

    /**
     * Creates a validated query builder ready for execution.
     *
     * Factory method that constructs a QueryBuilder with all requested operations
     * validated against resource-defined allow-lists. The returned instance has
     * its Eloquent query fully configured with fields, filters, relationships, and sorts.
     *
     * @param class-string                                                                                          $resource             Resource class defining model and allow-lists
     * @param array<string, array<int, string>>                                                                     $requestFields        Field selections requested by client
     * @param array<string, array<int, string>>                                                                     $allowedFields        Field selections permitted by resource
     * @param array<string, array<int, array{attribute: string, operator: string, value: mixed, boolean?: string}>> $requestFilters       Filter conditions requested by client
     * @param array<string, array<int, string>>                                                                     $allowedFilters       Filter attributes permitted by resource
     * @param array<string, array<int, string>>                                                                     $requestRelationships Relationships requested for eager loading
     * @param array<string, array<int, string>>                                                                     $allowedRelationships Relationships permitted by resource
     * @param array<string, array<int, array{attribute: string, direction: string}>>                                $requestSorts         Sort directives requested by client
     * @param array<string, array<int, string>>                                                                     $allowedSorts         Sort attributes permitted by resource
     *
     * @throws InvalidFieldsException        When any requested field is not authorized
     * @throws InvalidFiltersException       When any filter attribute is not authorized
     * @throws InvalidRelationshipsException When any relationship is not authorized
     * @throws InvalidSortsException         When any sort attribute is not authorized
     *
     * @return static Configured query builder with validated constraints
     */
    public static function for(
        string $resource,
        array $requestFields,
        array $allowedFields,
        array $requestFilters,
        array $allowedFilters,
        array $requestRelationships,
        array $allowedRelationships,
        array $requestSorts,
        array $allowedSorts,
    ): static {
        return new self(
            $resource,
            $requestFields,
            $allowedFields,
            $requestFilters,
            $allowedFilters,
            $requestRelationships,
            $allowedRelationships,
            $requestSorts,
            $allowedSorts,
        );
    }

    /**
     * Validates and collects requested fields against the allow-list.
     *
     * @throws InvalidFieldsException When a requested field is not in the allow-list
     */
    private function collectFields(): void
    {
        foreach ($this->requestFields as $resourceName => $resourceFields) {
            foreach ($resourceFields as $resourceField) {
                $allowedFields = $this->allowedFields[$resourceName] ?? [];

                throw_unless(in_array($resourceField, $allowedFields, true), InvalidFieldsException::create($resourceFields, $allowedFields));
            }

            $this->queryFields[$resourceName] = $resourceFields;
        }
    }

    /**
     * Validates and collects requested filters against the allow-list.
     *
     * @throws InvalidFiltersException When a filter attribute is not in the allow-list
     */
    private function collectFilters(): void
    {
        foreach ($this->requestFilters as $resourceName => $resourceFilters) {
            foreach ($resourceFilters as $resourceFilter) {
                $attribute = $resourceFilter['attribute'];
                $allowedFilters = $this->allowedFilters[$resourceName] ?? [];

                throw_unless(in_array($attribute, $allowedFilters, true), InvalidFiltersException::create([$attribute], $allowedFilters));

                $this->queryFilters[$resourceName][] = $resourceFilter;
            }
        }
    }

    /**
     * Validates and collects requested relationships against the allow-list.
     *
     * @throws InvalidRelationshipsException When a relationship is not in the allow-list
     */
    private function collectRelationships(): void
    {
        foreach ($this->requestRelationships as $resourceName => $relationships) {
            foreach ($relationships as $relationship) {
                $allowedRelationships = $this->allowedRelationships[$resourceName] ?? [];

                throw_unless(in_array($relationship, $allowedRelationships, true), InvalidRelationshipsException::create(
                    $this->requestRelationships[$resourceName],
                    $allowedRelationships,
                ));
            }
        }

        $this->queryRelationships = $this->requestRelationships;
    }

    /**
     * Validates and collects requested sorts against the allow-list.
     *
     * @throws InvalidSortsException When a sort attribute is not in the allow-list
     */
    private function collectSorts(): void
    {
        foreach ($this->requestSorts as $resourceName => $resourceSorts) {
            foreach ($resourceSorts as $resourceSort) {
                $allowedSorts = $this->allowedSorts[$resourceName] ?? [];

                throw_unless(in_array($resourceSort['attribute'], $allowedSorts, true), InvalidSortsException::create($resourceSorts, $allowedSorts));

                $this->querySorts[$resourceName][] = $resourceSort;
            }
        }
    }

    /**
     * Constructs the final Eloquent query from validated parameters.
     *
     * Applies all query constraints in the proper order: relationships with eager
     * loading, field selections for base and related models, filters with WHERE
     * conditions, and multi-level sorting. Handles nested relationship queries
     * through closure-based constraint composition.
     */
    private function applyToQuery(): void
    {
        // Arrange...
        $withs = [];

        // Relationships...
        foreach ($this->queryRelationships as $relationshipResource => $relationships) {
            foreach ($relationships as $relationship) {
                if ($relationshipResource === 'self') {
                    $withs[$relationship] = fn (Builder|Relation $query): Builder|Relation => $query;
                } elseif (array_key_exists($relationshipResource, $withs)) {
                    $withs[$relationship] = fn (Builder|Relation $query) => $withs[$relationshipResource]($query)->with($relationship);
                } else {
                    $withs[$relationship] = fn (Builder|Relation $query) => $query->with($relationship);
                }
            }
        }

        // Fields...
        foreach ($this->queryFields as $fieldResource => $fields) {
            if ($fieldResource === 'self') {
                $this->select($fields);
            } elseif (array_key_exists($fieldResource, $withs)) {
                $withs[$fieldResource] = fn (Builder|Relation $query) => $withs[$fieldResource]($query)->select($fields);
            } else {
                $withs[$fieldResource] = fn (Builder|Relation $query) => $query->select($fields);
            }
        }

        // Filters...
        foreach ($this->queryFilters as $filterResource => $filters) {
            $filterRelationships = [];

            foreach ($filters as $filter) {
                if ($filterResource === 'self') {
                    $this->applyFilter($this, $filter);
                } elseif (array_key_exists($filterResource, $filterRelationships)) {
                    $previousCallback = $filterRelationships[$filterResource];
                    $filterRelationships[$filterResource] = fn (Builder|Relation $query): Builder|Relation|QueryBuilder => $this->applyFilter($previousCallback($query), $filter);
                } else {
                    $filterRelationships[$filterResource] = fn (Builder|Relation $query): Builder|Relation|QueryBuilder => $this->applyFilter($query, $filter);
                }
            }

            foreach ($filterRelationships as $filterRelationshipName => $filterRelationshipQuery) {
                $this->whereHas($filterRelationshipName, $filterRelationshipQuery);
            }
        }

        // Sorts...
        foreach ($this->querySorts as $sortResource => $sorts) {
            foreach ($sorts as $sort) {
                if ($sortResource === 'self') {
                    $this->orderBy($sort['attribute'], $sort['direction']);
                } elseif (array_key_exists($sortResource, $withs)) {
                    $withs[$sortResource] = fn (Builder|Relation $query) => $withs[$sortResource]($query)->orderBy($sort['attribute'], $sort['direction']);
                } else {
                    $withs[$sortResource] = fn (Builder|Relation $query) => $query->orderBy($sort['attribute'], $sort['direction']);
                }
            }
        }

        // Act...
        $this->with($withs);
    }

    /**
     * Applies a filter condition to the query using the appropriate WHERE clause.
     *
     * Translates Forrst filter operators into Eloquent query methods, supporting
     * comparisons (equals, greater than, etc.), pattern matching (like), range
     * checks (between), set operations (in/not in), and null checks. Honors the
     * boolean conjunction (AND/OR) specified in the filter.
     *
     * @param Builder<Model>|Relation<Model, Model, mixed>|self                          $query  Query builder to modify
     * @param array{attribute: string, operator: string, value: mixed, boolean?: string} $filter Filter specification with operator and value
     *
     * @throws InvalidFiltersException When the operator is not recognized or supported
     *
     * @return Builder<Model>|Relation<Model, Model, mixed>|self Query builder with filter constraint applied
     */
    private function applyFilter(Builder|Relation|self $query, array $filter): Builder|Relation|self
    {
        $attribute = $filter['attribute'];
        $value = $filter['value'] ?? null;
        $boolean = $filter['boolean'] ?? 'and';

        match ($filter['operator']) {
            'equals' => $query->where($attribute, '=', $value, $boolean),
            'not_equals' => $query->where($attribute, '!=', $value, $boolean),
            'greater_than' => $query->where($attribute, '>', $value, $boolean),
            'greater_than_or_equal_to' => $query->where($attribute, '>=', $value, $boolean),
            'less_than' => $query->where($attribute, '<', $value, $boolean),
            'less_than_or_equal_to' => $query->where($attribute, '<=', $value, $boolean),
            'like' => $query->where($attribute, 'like', $value, $boolean),
            'not_like' => $query->where($attribute, 'not like', $value, $boolean),
            'in' => $query->whereIn($attribute, $value, $boolean),
            'not_in' => $query->whereNotIn($attribute, $value, $boolean),
            'between' => $query->whereBetween($attribute, is_array($value) ? $value : [], $boolean),
            'not_between' => $query->whereNotBetween($attribute, is_array($value) ? $value : [], $boolean),
            'is_null' => $query->whereNull($attribute, $boolean),
            'is_not_null' => $query->whereNotNull($attribute, $boolean),
            default => throw InvalidFiltersException::create(
                [$attribute],
                $this->allowedFilters !== [] ? array_merge(...array_values($this->allowedFilters)) : [],
            ),
        };

        return $query;
    }
}

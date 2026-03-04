<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Resources;

use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Exceptions\InvalidModelException;
use Cline\Forrst\QueryBuilders\QueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Override;

use function array_keys;
use function class_basename;
use function resolve;
use function sprintf;
use function throw_unless;

/**
 * Base resource transformer for Eloquent model instances.
 *
 * Provides convention-based transformation of Eloquent models into Forrst resource
 * objects with automatic type derivation, query building, and relationship handling.
 * Integrates with QueryBuilder for secure field selection, filtering, and sorting
 * based on resource-defined allow-lists.
 *
 * Resource classes extending this base automatically infer the model class from
 * their own class name (e.g., "OrderResource" maps to "App\Models\Order").
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/resource-objects
 */
abstract class AbstractModelResource extends AbstractResource
{
    /**
     * Creates a resource wrapping an Eloquent model instance.
     *
     * @param Model $model Eloquent model to transform. The model's attributes and loaded
     *                     relationships will be serialized into the Forrst resource object
     *                     according to the resource's field and relationship configuration.
     */
    public function __construct(
        private readonly Model $model,
    ) {}

    /**
     * Constructs a validated QueryBuilder for fetching model instances.
     *
     * Creates a QueryBuilder configured with the request's field selections, filters,
     * relationships, and sorts, all validated against the resource's allow-lists.
     * The returned builder is ready for execution (e.g., ->get(), ->paginate()).
     *
     * @param RequestObjectData $request Forrst request containing query parameters extracted
     *                                   from the call.arguments (fields, filters, relationships, sorts)
     *
     * @return QueryBuilder Configured and validated Eloquent query builder
     */
    public static function query(RequestObjectData $request): QueryBuilder
    {
        /** @var array<string, array<int, string>> $requestFields */
        $requestFields = (array) $request->getArgument('fields', []);

        /** @var array<string, array<int, array{attribute: string, operator: string, value: mixed, boolean?: string}>> $requestFilters */
        $requestFilters = (array) $request->getArgument('filters', []);

        /** @var array<string, array<int, string>> $requestRelationships */
        $requestRelationships = (array) $request->getArgument('relationships', []);

        /** @var array<string, array<int, array{attribute: string, direction: string}>> $requestSorts */
        $requestSorts = (array) $request->getArgument('sorts', []);

        /** @var array<string, array<int, string>> $allowedFilters */
        $allowedFilters = static::getFilters();

        /** @var array<string, array<int, string>> $allowedRelationships */
        $allowedRelationships = static::getRelationships();

        /** @var array<string, array<int, string>> $allowedSorts */
        $allowedSorts = static::getSorts();

        return QueryBuilder::for(
            resource: static::class,
            requestFields: $requestFields,
            allowedFields: static::getFields(),
            requestFilters: $requestFilters,
            allowedFilters: $allowedFilters,
            requestRelationships: $requestRelationships,
            allowedRelationships: $allowedRelationships,
            requestSorts: $requestSorts,
            allowedSorts: $allowedSorts,
        );
    }

    /**
     * Returns the fully qualified Eloquent model class name.
     *
     * Automatically derives the model class by removing "Resource" suffix from
     * the resource class name and prepending "App\Models\" namespace.
     * Example: "OrderResource" → "App\Models\Order".
     *
     * @return string Fully qualified model class name
     */
    public static function getModel(): string
    {
        return sprintf(
            'App\\Models\\%s',
            Str::beforeLast(class_basename(static::class), 'Resource'),
        );
    }

    /**
     * Returns the database table name for the associated model.
     *
     * Resolves the model instance and retrieves its table name, supporting
     * both default table naming and custom $table property configurations.
     *
     * @return string Database table name (e.g., "orders")
     */
    public static function getModelTable(): string
    {
        $model = resolve(static::getModel());

        throw_unless($model instanceof Model, InvalidModelException::notInstanceOf(Model::class));

        return $model->getTable();
    }

    /**
     * Returns the resource type identifier for the model.
     *
     * Converts the model's table name to singular form for use as the
     * resource type in Forrst responses. Example: "orders" → "order".
     *
     * @return string Singular resource type identifier
     */
    public static function getModelType(): string
    {
        return Str::singular(static::getModelTable());
    }

    /**
     * Returns the resource type for this instance.
     *
     * @return string Singular form of the model's table name
     */
    #[Override()]
    public function getType(): string
    {
        return Str::singular(static::getModelTable());
    }

    /**
     * Returns the unique identifier for this resource.
     *
     * Extracts and stringifies the model's primary key value.
     * Assumes standard "id" primary key.
     *
     * @return string String representation of the model's ID
     */
    #[Override()]
    public function getId(): string
    {
        // @phpstan-ignore-next-line
        return (string) $this->model->id;
    }

    /**
     * Returns resource attributes filtered by allow-list configuration.
     *
     * Extracts model attributes that are in the resource's allowed fields,
     * excluding the "id" field (which is in a separate resource property)
     * and any eager-loaded relationship data (which is in relationships property).
     *
     * @return array<string, mixed> Filtered model attributes
     */
    #[Override()]
    public function getAttributes(): array
    {
        /** @var array<string, mixed> $rawAttributes */
        $rawAttributes = $this->model->toArray();

        /** @var array<string, mixed> $attributes */
        $attributes = Arr::only($rawAttributes, static::getFields()['self']);

        Arr::forget($attributes, 'id');

        foreach (array_keys($this->getRelations()) as $relation) {
            if (!Arr::has($attributes, $relation)) {
                continue;
            }

            Arr::forget($attributes, $relation);
        }

        /** @var array<string, mixed> $result */
        $result = $attributes;

        return $result;
    }

    /**
     * Returns eager-loaded Eloquent relationships for this resource.
     *
     * Provides access to all relationships loaded on the model, typically
     * via QueryBuilder's with() constraints based on the request's
     * relationships parameter.
     *
     * @return array<string, mixed> Loaded relationships keyed by relation name
     */
    #[Override()]
    public function getRelations(): array
    {
        // @phpstan-ignore return.type
        return $this->model->getRelations();
    }
}

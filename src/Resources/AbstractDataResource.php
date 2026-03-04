<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Resources;

use Illuminate\Support\Str;
use Override;
use Spatie\LaravelData\Data;

use function class_basename;
use function data_get;

/**
 * Resource transformer for Spatie Laravel Data transfer objects.
 *
 * Enables transformation of Spatie Data DTOs into Forrst resource objects without
 * requiring Eloquent models. Automatically extracts the resource type from the
 * Data class name (e.g., "OrderData" becomes "order") and exposes all DTO
 * properties as resource attributes.
 *
 * Useful for transforming API responses, computed values, or aggregated data
 * that doesn't map directly to a database model.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/resource-objects
 */
abstract class AbstractDataResource extends AbstractResource
{
    /**
     * Creates a resource wrapping a Spatie Data DTO.
     *
     * @param Data $model Spatie Laravel Data object containing the source data to transform.
     *                    The DTO's properties will be exposed as resource attributes.
     */
    public function __construct(
        private readonly Data $model,
    ) {}

    /**
     * Derives the resource type identifier from the Data class name.
     *
     * Automatically extracts the type by removing the "Data" suffix and singularizing.
     * Examples: "OrderData" → "order", "ProductsData" → "product".
     *
     * @return string Singular resource type identifier
     */
    #[Override()]
    public function getType(): string
    {
        return Str::singular(Str::beforeLast(class_basename($this->model), 'Data'));
    }

    /**
     * Extracts the resource identifier from the Data object's id property.
     *
     * @return string String representation of the DTO's id field
     */
    #[Override()]
    public function getId(): string
    {
        // @phpstan-ignore-next-line - data_get can return mixed, but we handle all cases by casting to string
        return (string) data_get($this->model, 'id');
    }

    /**
     * Returns all Data object properties as resource attributes.
     *
     * Serializes the entire DTO to an array, exposing all properties as
     * attributes in the Forrst resource object.
     *
     * @return array<string, mixed> DTO properties as associative array
     */
    #[Override()]
    public function getAttributes(): array
    {
        /** @var array<string, mixed> */
        return $this->model->toArray();
    }

    /**
     * Returns relationships for this resource.
     *
     * Data resources do not support relationships by default since DTOs lack
     * Eloquent's relationship loading. Override in subclasses to manually
     * define relationships if needed.
     *
     * @return array<string, mixed> Empty array (no relationships by default)
     */
    #[Override()]
    public function getRelations(): array
    {
        return [];
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Normalizers;

use Cline\Forrst\Data\ResourceIdentifierData;
use Cline\Forrst\Data\ResourceObjectData;
use Cline\Forrst\Repositories\ResourceRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Converts Eloquent models into JSON:API resource object structures.
 *
 * Transforms Laravel Eloquent models into ResourceObjectData instances by resolving
 * the appropriate resource transformer, converting the model's attributes, and
 * recursively normalizing any loaded relationships while preserving their cardinality.
 *
 * Per JSON:API compound document specification, relationships contain only resource
 * identifiers (type+id), with full resource objects collected for the document's
 * `included` array. This enables efficient data transfer and prevents duplication
 * when the same resource appears in multiple relationships.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://jsonapi.org/format/#document-compound-documents
 * @see https://docs.cline.sh/forrst/resource-objects
 *
 * @psalm-immutable
 */
final readonly class ModelNormalizer
{
    /**
     * Normalizes an Eloquent model into a resource object structure.
     *
     * Resolves the model's resource transformer from the ResourceRepository, converts
     * the model's attributes, and recursively processes any eager-loaded relationships.
     * Use normalizeWithIncludes() when you need the included resources for compound documents.
     *
     * @param Model $model The Eloquent model instance to normalize
     *
     * @return ResourceObjectData Normalized resource object containing type, id, attributes, and relationships
     */
    public static function normalize(Model $model): ResourceObjectData
    {
        return self::normalizeWithIncludes($model)->resource;
    }

    /**
     * Normalizes an Eloquent model with proper JSON:API compound document structure.
     *
     * Returns both the normalized resource (with relationship identifiers only) and
     * a collection of included resources for the document's `included` array.
     * Relationships contain resource linkage (type+id), while full resource objects
     * go in the included array. Automatically deduplicates included resources by
     * their type:id combination.
     *
     * @param Model $model The Eloquent model instance to normalize with eager-loaded relationships
     *
     * @return NormalizationResult Result containing the primary resource and all included related resources
     */
    public static function normalizeWithIncludes(Model $model): NormalizationResult
    {
        $resource = ResourceRepository::get($model);
        $pendingResourceObject = $resource->toArray();
        $included = [];

        foreach ($resource->getRelations() as $relationName => $relationModels) {
            if ($relationModels === null) {
                continue;
            }

            // Detect relationship cardinality by checking if the relation returns a single model
            $isOneToOne = $relationModels instanceof Model;

            if ($isOneToOne) {
                $relationModels = Arr::wrap($relationModels);
            }

            /** @var Model $relationModel */
            /** @phpstan-ignore foreach.nonIterable */
            foreach ($relationModels as $relationModel) {
                // Get full resource for the included array
                $relatedResource = ResourceRepository::get($relationModel);
                $relatedResourceObject = ResourceObjectData::from($relatedResource->toArray());

                // Add to included array, keyed by type:id for deduplication
                $includeKey = $relatedResourceObject->type.':'.$relatedResourceObject->id;
                $included[$includeKey] = $relatedResourceObject;

                // Create resource identifier for relationship linkage
                $identifier = ResourceIdentifierData::fromResource($relatedResourceObject);

                if ($isOneToOne) {
                    // Single relationship: { data: { type, id } }
                    /** @phpstan-ignore offsetAccess.nonOffsetAccessible */
                    $pendingResourceObject['relationships'][$relationName] = [
                        'data' => $identifier->toArray(),
                    ];
                } else {
                    // Collection relationship: { data: [{ type, id }, ...] }
                    /** @phpstan-ignore offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible */
                    $pendingResourceObject['relationships'][$relationName]['data'][] = $identifier->toArray();
                }
            }
        }

        return new NormalizationResult(
            resource: ResourceObjectData::from($pendingResourceObject),
            included: $included,
        );
    }
}

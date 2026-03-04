<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Normalizers;

use Cline\Forrst\Contracts\ResourceInterface;
use Cline\Forrst\Data\ResourceObjectData;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Normalizes resource objects into Forrst resource object structures.
 *
 * Transforms ResourceInterface instances into ResourceObjectData by extracting
 * attributes and processing relationships. Automatically detects relationship
 * cardinality through pluralization conventions: singular names indicate
 * one-to-one relationships, plural names indicate one-to-many relationships.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/resource-objects
 *
 * @psalm-immutable
 */
final readonly class ResourceNormalizer
{
    /**
     * Normalizes a resource into a Forrst-compliant resource object structure.
     *
     * Extracts the resource's attributes and recursively processes relationships,
     * automatically determining cardinality through naming conventions. Singular
     * relationship names (e.g., "author") are treated as one-to-one, plural names
     * (e.g., "comments") as one-to-many collections.
     *
     * @param ResourceInterface $resource Resource instance to normalize
     *
     * @return ResourceObjectData Normalized structure with type, id, attributes, and relationships
     */
    public static function normalize(ResourceInterface $resource): ResourceObjectData
    {
        $pendingResourceObject = $resource->toArray();

        foreach ($resource->getRelations() as $relationName => $relationModels) {
            // Detect relationship cardinality by checking if the relation name is singular
            $isOneToOne = Str::plural($relationName) !== $relationName;

            if ($isOneToOne) {
                $relationModels = Arr::wrap($relationModels);
            }

            /** @phpstan-ignore foreach.nonIterable */
            foreach ($relationModels as $relationship) {
                if ($isOneToOne) {
                    /** @phpstan-ignore offsetAccess.nonOffsetAccessible */
                    $pendingResourceObject['relationships'][$relationName] = $relationship;
                } else {
                    /** @phpstan-ignore offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible */
                    $pendingResourceObject['relationships'][$relationName][] = $relationship;
                }
            }
        }

        return ResourceObjectData::from($pendingResourceObject);
    }
}

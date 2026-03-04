<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Normalizers;

use Cline\Forrst\Data\ResourceObjectData;

use function array_values;

/**
 * Normalization result containing both the primary resource and included resources.
 *
 * Per JSON:API compound document specification, relationships should contain
 * resource identifiers only, with full resource objects placed in the document's
 * `included` array. This class holds both pieces needed for proper JSON:API
 * document construction, with utilities for merging multiple results.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://jsonapi.org/format/#document-compound-documents
 * @see https://docs.cline.sh/forrst/resource-objects
 * @psalm-immutable
 */
final readonly class NormalizationResult
{
    /**
     * Create a new normalization result.
     *
     * @param ResourceObjectData                $resource The normalized primary resource with
     *                                                    relationship identifiers (not full objects),
     *                                                    ready for the document's `data` field.
     * @param array<string, ResourceObjectData> $included Related resources keyed by "type:id" for
     *                                                    automatic deduplication. Full resource objects
     *                                                    to be placed in document's `included` array.
     */
    public function __construct(
        public ResourceObjectData $resource,
        public array $included = [],
    ) {}

    /**
     * Merge included resources from multiple normalization results.
     *
     * Combines the included resources from multiple normalization results while
     * maintaining deduplication by type:id. Later occurrences of the same resource
     * overwrite earlier ones. Useful when normalizing multiple models for a
     * collection response.
     *
     * @param array<int, self> $results Array of normalization results to merge
     *
     * @return array<string, ResourceObjectData> Merged included resources keyed by "type:id" for deduplication
     */
    public static function mergeIncluded(array $results): array
    {
        $merged = [];

        foreach ($results as $result) {
            foreach ($result->included as $key => $resource) {
                $merged[$key] = $resource;
            }
        }

        return $merged;
    }

    /**
     * Get included resources as a flat array without the deduplication keys.
     *
     * Converts the type:id keyed array to a simple indexed array suitable for
     * JSON serialization in the document's `included` field.
     *
     * @return array<int, ResourceObjectData> Included resources as an indexed array
     */
    public function getIncludedArray(): array
    {
        return array_values($this->included);
    }
}

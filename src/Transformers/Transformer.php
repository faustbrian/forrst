<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Transformers;

use Cline\Forrst\Contracts\ResourceInterface;
use Cline\Forrst\Data\DocumentData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Data\ResourceObjectData;
use Cline\Forrst\Normalizers\ModelNormalizer;
use Cline\Forrst\Normalizers\ResourceNormalizer;
use Cline\Forrst\QueryBuilders\QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

use function array_map;
use function array_values;
use function is_int;
use function is_string;

/**
 * Transforms Eloquent models and resources into standardized Forrst document structures.
 *
 * Provides methods for transforming single items, collections, and paginated results
 * into Forrst response documents with proper resource normalization and relationship
 * handling. Supports cursor pagination, offset pagination, and simple pagination
 * with metadata for page navigation.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/resource-objects
 *
 * @psalm-immutable
 */
final readonly class Transformer
{
    /**
     * Create a new transformer instance.
     *
     * @param RequestObjectData $requestObject The Forrst request containing pagination
     *                                         parameters and other transformation options.
     *                                         Used to extract page size, cursor, and page
     *                                         number from request parameters.
     */
    private function __construct(
        private RequestObjectData $requestObject,
    ) {}

    /**
     * Create a new transformer instance for the given request.
     *
     * Factory method providing a cleaner API for instantiating transformers with
     * the necessary request context for pagination and transformation.
     *
     * @param  RequestObjectData $requestObject Request containing transformation parameters
     * @return self              New transformer instance
     */
    public static function create(RequestObjectData $requestObject): self
    {
        return new self($requestObject);
    }

    /**
     * Transform a single model or resource into a Forrst document.
     *
     * Normalizes the item using either ModelNormalizer for Eloquent models or
     * ResourceNormalizer for resource objects, ensuring consistent Forrst
     * document structure regardless of the input type.
     *
     * Per JSON:API compound document specification, relationships contain
     * resource identifiers only, with full related resources in the `included` array.
     *
     * @param  Model|ResourceInterface $item Model or resource to transform
     * @return DocumentData            Forrst document containing the transformed item and included resources
     */
    public function item(Model|ResourceInterface $item): DocumentData
    {
        if ($item instanceof Model) {
            $result = ModelNormalizer::normalizeWithIncludes($item);

            $document = [
                'data' => $result->resource->toArray(),
            ];

            if ($result->included !== []) {
                $document['included'] = array_map(
                    fn (ResourceObjectData $r): array => $r->toArray(),
                    $result->getIncludedArray(),
                );
            }

            return DocumentData::from($document);
        }

        return DocumentData::from([
            'data' => ResourceNormalizer::normalize($item)->toArray(),
        ]);
    }

    /**
     * Transform a collection of models or resources into a Forrst document.
     *
     * Iterates through the collection, normalizing each item based on its type,
     * and returns a document with all transformed items in the data array.
     * Related resources are automatically deduplicated by type:id key and placed
     * in the included array for compound document structure.
     *
     * @param  Collection<int, Model|ResourceInterface> $collection Collection of items to transform
     * @return DocumentData                             Forrst document with data array and optional included array
     */
    public function collection(Collection $collection): DocumentData
    {
        $data = [];
        $allIncluded = [];

        foreach ($collection as $item) {
            if ($item instanceof Model) {
                $result = ModelNormalizer::normalizeWithIncludes($item);
                $data[] = $result->resource->toArray();

                // Merge included resources, maintaining deduplication by type:id key
                foreach ($result->included as $key => $resource) {
                    $allIncluded[$key] = $resource;
                }
            } else {
                $data[] = ResourceNormalizer::normalize($item)->toArray();
            }
        }

        $document = ['data' => $data];

        if ($allIncluded !== []) {
            $document['included'] = array_map(
                fn (ResourceObjectData $r): array => $r->toArray(),
                array_values($allIncluded),
            );
        }

        return DocumentData::from($document);
    }

    /**
     * Execute a cursor-paginated query and transform results into a Forrst document.
     *
     * Applies cursor-based pagination to the query using parameters from the request
     * (page.size and page.cursor). Includes pagination metadata with cursor values
     * for navigating to previous/next pages. Cursor pagination is efficient for
     * large datasets as it doesn't require counting total records.
     *
     * @param  Builder<Model>|QueryBuilder $query Query builder to paginate and transform
     * @return DocumentData                Forrst document with data array and pagination metadata
     */
    public function cursorPaginate(Builder|QueryBuilder $query): DocumentData
    {
        $pageSize = $this->requestObject->getArgument('page.size', '100');

        /** @phpstan-var int|string|null $pageSize */
        $pageCursor = $this->requestObject->getArgument('page.cursor');
        /** @phpstan-var string|null $pageCursor */

        /** @var CursorPaginator<int, Model> $paginator */
        $paginator = $query->cursorPaginate(
            is_int($pageSize) ? $pageSize : (int) $pageSize,
            ['*'],
            'page[cursor]',
            is_string($pageCursor) ? $pageCursor : (string) $pageCursor,
        );

        /** @var Collection<int, Model|ResourceInterface> $collection */
        $collection = $paginator->getCollection();
        $document = self::collection($collection)->toArray();

        if ($paginator->hasPages()) {
            $document['meta'] = [
                'page' => [
                    'cursor' => [
                        'self' => $paginator->cursor()?->encode(),
                        'prev' => $paginator->previousCursor()?->encode(),
                        'next' => $paginator->nextCursor()?->encode(),
                    ],
                ],
            ];
        }

        return DocumentData::from($document);
    }

    /**
     * Execute an offset-paginated query and transform results into a Forrst document.
     *
     * Applies traditional offset-based pagination using page number and size from
     * the request (page.number and page.size). Includes pagination metadata with
     * current, previous, and next page numbers. This method counts total records,
     * making it suitable for scenarios requiring total page count display.
     *
     * @param  Builder<Model>|QueryBuilder $query Query builder to paginate and transform
     * @return DocumentData                Forrst document with data array and page number metadata
     */
    public function paginate(Builder|QueryBuilder $query): DocumentData
    {
        $pageSize = $this->requestObject->getArgument('page.size', '100');

        /** @phpstan-var int|string|null $pageSize */
        $pageNumber = $this->requestObject->getArgument('page.number');
        /** @phpstan-var int|string|null $pageNumber */

        /** @var LengthAwarePaginator<int, Model> $paginator */
        $paginator = $query->paginate(
            is_int($pageSize) ? $pageSize : (int) $pageSize,
            ['*'],
            'page[number]',
            is_int($pageNumber) ? $pageNumber : (int) $pageNumber,
        );

        /** @var Collection<int, Model|ResourceInterface> $collection */
        $collection = $paginator->getCollection();
        $document = self::collection($collection)->toArray();

        if ($paginator->hasPages()) {
            $document['meta'] = [
                'page' => [
                    'number' => [
                        'self' => $paginator->currentPage(),
                        'prev' => $paginator->onFirstPage() ? null : $paginator->currentPage() - 1,
                        'next' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
                    ],
                ],
            ];
        }

        return DocumentData::from($document);
    }

    /**
     * Execute a simple paginated query and transform results into a Forrst document.
     *
     * Applies lightweight pagination that only determines if more pages exist without
     * counting total records. Uses page number and size from the request. This is
     * more performant than paginate() for large datasets when total count isn't needed.
     *
     * @param  Builder<Model>|QueryBuilder $query Query builder to paginate and transform
     * @return DocumentData                Forrst document with data array and basic page metadata
     */
    public function simplePaginate(Builder|QueryBuilder $query): DocumentData
    {
        $pageSize = $this->requestObject->getArgument('page.size', '100');

        /** @phpstan-var int|string|null $pageSize */
        $pageNumber = $this->requestObject->getArgument('page.number');
        /** @phpstan-var int|string|null $pageNumber */

        /** @var Paginator<int, Model> $paginator */
        $paginator = $query->simplePaginate(
            is_int($pageSize) ? $pageSize : (int) $pageSize,
            ['*'],
            'page[number]',
            is_int($pageNumber) ? $pageNumber : (int) $pageNumber,
        );

        /** @var Collection<int, Model|ResourceInterface> $collection */
        $collection = $paginator->getCollection();
        $document = self::collection($collection)->toArray();

        if ($paginator->hasPages()) {
            $document['meta'] = [
                'page' => [
                    'number' => [
                        'self' => $paginator->currentPage(),
                        'prev' => $paginator->onFirstPage() ? null : $paginator->currentPage() - 1,
                        'next' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
                    ],
                ],
            ];
        }

        return DocumentData::from($document);
    }
}

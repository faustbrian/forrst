<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Resources;

use Cline\Forrst\Contracts\ResourceInterface;
use Override;

/**
 * Base resource class defining the resource transformation contract.
 *
 * Provides default implementations for field, filter, relationship, and sort
 * configuration methods. Concrete resource classes should override these methods
 * to define their specific allowed operations and data structure for API access
 * control and query building.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/resource-objects
 */
abstract class AbstractResource implements ResourceInterface
{
    /**
     * Get the fields that can be queried and returned in responses.
     *
     * Returns an array defining which model attributes can be requested via the
     * QueryBuilder. Override this method to specify allowed fields for the resource.
     * The array structure typically includes 'self' for direct fields and relationship
     * keys for nested fields.
     *
     * @return array<string, array<int, string>> Array of allowed fields grouped by context
     */
    public static function getFields(): array
    {
        return [];
    }

    /**
     * Get the filters that can be applied to resource queries.
     *
     * Returns an array of allowed filter definitions that the QueryBuilder can apply.
     * Override this method to specify which fields can be filtered and how.
     * Each filter should define the field name and filter type (exact, partial, etc.).
     *
     * @return array<int, string> Array of allowed filter configurations
     */
    public static function getFilters(): array
    {
        return [];
    }

    /**
     * Get the relationships that can be eager-loaded with this resource.
     *
     * Returns an array of Eloquent relationship names that can be included in queries.
     * Override this method to specify which relationships are allowed to be loaded.
     * This prevents unauthorized access to related data through the API.
     *
     * @return array<int, string> Array of allowed relationship names
     */
    public static function getRelationships(): array
    {
        return [];
    }

    /**
     * Get the fields that can be used for sorting query results.
     *
     * Returns an array of field names that are allowed in ORDER BY clauses.
     * Override this method to specify which fields can be used for sorting.
     * This prevents sorting on computed or sensitive fields that could impact performance.
     *
     * @return array<int, string> Array of sortable field names
     */
    public static function getSorts(): array
    {
        return [];
    }

    /**
     * Convert the resource to its array representation for Forrst responses.
     *
     * Constructs a standardized resource object structure containing the resource type,
     * unique identifier, and attributes. This format ensures consistent API responses
     * across all resource types in the Forrst specification.
     *
     * @return array{type: string, id: string, attributes: array<string, mixed>} Standardized resource structure
     */
    #[Override()]
    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'id' => $this->getId(),
            'attributes' => $this->getAttributes(),
        ];
    }
}

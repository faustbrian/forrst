<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Data;

use Cline\Forrst\Exceptions\EmptyFieldException;
use Cline\Forrst\Exceptions\FieldExceedsMaxLengthException;
use Cline\Forrst\Exceptions\InvalidFieldTypeException;

use function is_string;
use function mb_strlen;

/**
 * JSON:API compliant resource identifier for relationship linkage.
 *
 * Resource identifiers provide lightweight references to related resources
 * without including their full data representations. They are used in JSON:API
 * relationship objects to indicate connections between resources. Full resource
 * representations should be placed in the document's included array when needed.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/resource-objects
 * @see https://jsonapi.org/format/#document-resource-identifier-objects
 */
final class ResourceIdentifierData extends AbstractData
{
    /**
     * Maximum length for type field to prevent memory exhaustion.
     */
    private const int MAX_TYPE_LENGTH = 255;

    /**
     * Maximum length for id field to prevent memory exhaustion.
     */
    private const int MAX_ID_LENGTH = 255;

    /**
     * Create a new resource identifier data instance.
     *
     * @param string $type The resource type identifier following JSON:API naming conventions,
     *                     typically plural, lowercase, and hyphenated (e.g., 'users', 'order-items').
     *                     Used to categorize and route resources within the API.
     * @param string $id   The unique identifier for this specific resource instance, typically
     *                     the primary key value cast as a string. Must be unique within the
     *                     resource type namespace.
     *
     * @throws EmptyFieldException            If type or id are empty
     * @throws FieldExceedsMaxLengthException If type or id exceed maximum length
     */
    public function __construct(
        public readonly string $type,
        public readonly string $id,
    ) {
        if ($type === '') {
            throw EmptyFieldException::forField('type');
        }

        if (mb_strlen($type) > self::MAX_TYPE_LENGTH) {
            throw FieldExceedsMaxLengthException::forField('type', self::MAX_TYPE_LENGTH);
        }

        if ($id === '') {
            throw EmptyFieldException::forField('id');
        }

        if (mb_strlen($id) > self::MAX_ID_LENGTH) {
            throw FieldExceedsMaxLengthException::forField('id', self::MAX_ID_LENGTH);
        }
    }

    /**
     * Create a resource identifier from an array of data.
     *
     * Factory method for creating a resource identifier from an array with
     * automatic validation and type checking.
     *
     * @param array<string, mixed> $data Array containing type and id fields
     *
     * @throws InvalidFieldTypeException If type or id are missing or not strings
     * @return self                      Resource identifier instance
     */
    public static function createFromArray(array $data): self
    {
        return new self(
            type: isset($data['type']) && is_string($data['type']) ? $data['type'] : throw InvalidFieldTypeException::forField('type', 'string', $data['type'] ?? null),
            id: isset($data['id']) && is_string($data['id']) ? $data['id'] : throw InvalidFieldTypeException::forField('id', 'string', $data['id'] ?? null),
        );
    }

    /**
     * Create a resource identifier from a resource object.
     *
     * Extracts the type and id fields from a full resource object to create
     * a lightweight identifier suitable for relationship linkage.
     *
     * @param ResourceObjectData $resource The full resource object to extract identifier from
     *
     * @return self Resource identifier containing only type and id
     */
    public static function fromResource(ResourceObjectData $resource): self
    {
        return new self(
            type: $resource->type,
            id: $resource->id,
        );
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Data\Errors;

use Cline\Forrst\Data\AbstractData;
use Override;

use function is_int;
use function is_string;

/**
 * Represents the source location of an error in a Forrst request.
 *
 * Identifies the specific part of the request that caused an error using
 * either a JSON Pointer for field errors or a byte position for parse errors.
 * A source object MUST contain `pointer` OR `position`, not both.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://datatracker.ietf.org/doc/html/rfc6901
 */
final class SourceData extends AbstractData
{
    /**
     * Create a new error source information object.
     *
     * @param null|string $pointer  JSON Pointer (RFC 6901) reference to the specific
     *                              field or location in the request document that caused
     *                              the error. Uses slash-separated path syntax like
     *                              "/call/arguments/customer_id" for precise error location.
     * @param null|int    $position Zero-indexed byte offset where parsing failed.
     *                              Used only for parse errors when the request is not valid JSON.
     */
    public function __construct(
        public readonly ?string $pointer = null,
        public readonly ?int $position = null,
    ) {}

    /**
     * Create a source from an array of data.
     *
     * Factory method for creating a SourceData instance from array data,
     * typically from deserialized JSON. Accepts either pointer or position.
     *
     * @param array<string, mixed> $data Array containing 'pointer' or 'position' key
     *
     * @return self SourceData instance with data populated
     */
    public static function createFromArray(array $data): self
    {
        return new self(
            pointer: isset($data['pointer']) && is_string($data['pointer']) ? $data['pointer'] : null,
            position: isset($data['position']) && is_int($data['position']) ? $data['position'] : null,
        );
    }

    /**
     * Create a source pointing to a request field.
     *
     * Factory method for creating a source that identifies a specific field
     * in the request using JSON Pointer notation.
     *
     * @param string $pointer JSON Pointer path to the problematic field
     *
     * @return self SourceData instance with pointer set
     */
    public static function pointer(string $pointer): self
    {
        return new self(pointer: $pointer);
    }

    /**
     * Create a source pointing to a parse position.
     *
     * Factory method for creating a source that identifies where parsing
     * failed in the request body. Used for JSON syntax errors.
     *
     * @param int $position Zero-indexed byte offset where parsing failed
     *
     * @return self SourceData instance with position set
     */
    public static function position(int $position): self
    {
        return new self(position: $position);
    }

    /**
     * Convert to array representation.
     *
     * Serializes only the non-null member (pointer or position) to maintain
     * protocol compliance. A source must contain exactly one member.
     *
     * @return array<string, mixed> Array with pointer or position key
     */
    #[Override()]
    public function toArray(): array
    {
        $result = [];

        if ($this->pointer !== null) {
            $result['pointer'] = $this->pointer;
        }

        if ($this->position !== null) {
            $result['position'] = $this->position;
        }

        return $result;
    }
}

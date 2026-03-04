<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Data;

use Cline\Forrst\Exceptions\MissingRequiredFieldException;
use InvalidArgumentException;

use function count;
use function is_array;

/**
 * Document wrapper for Forrst responses following JSON:API patterns.
 *
 * Provides a standardized top-level structure for RPC responses that mirrors
 * the JSON:API document format. This ensures consistent response shapes across
 * all RPC methods and facilitates client-side response handling.
 *
 * The document structure separates successful response data from error
 * information and optional metadata, following JSON:API conventions. Per the
 * JSON:API specification, the data and errors members must not coexist in
 * the same document.
 *
 * Related resources in compound documents are placed in the included array,
 * deduplicated by type and id, while relationships contain only resource
 * identifiers for linkage.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/document-structure
 * @see https://jsonapi.org/format/#document-top-level
 * @see https://jsonapi.org/format/#document-compound-documents
 */
final class DocumentData extends AbstractData
{
    /**
     * Create a new Forrst document response.
     *
     * @param array<string, mixed>                  $data     Primary response payload containing the method's
     *                                                        result data. For successful responses, this holds
     *                                                        the actual return value from the RPC method. Should
     *                                                        be structured according to the method's result
     *                                                        content descriptor.
     * @param null|array<int, array<string, mixed>> $included Optional array of related resource objects
     *                                                        for compound documents. Contains full representations
     *                                                        of resources referenced via identifiers in relationships.
     *                                                        Each resource appears only once, deduplicated by type+id.
     * @param null|array<int, mixed>                $errors   Optional array of error objects for failed
     *                                                        requests. Each error should include code, message,
     *                                                        and optional data fields. Null for successful
     *                                                        responses. Following JSON:API, errors and data
     *                                                        should not both be present.
     * @param null|array<string, mixed>             $meta     Optional metadata object containing non-standard
     *                                                        information about the response such as pagination
     *                                                        details, timing information, or API version. Can
     *                                                        be present with either successful or error responses.
     */
    public function __construct(
        public readonly array $data,
        public readonly ?array $included = null,
        public readonly ?array $errors = null,
        public readonly ?array $meta = null,
    ) {}

    /**
     * Create a DocumentData instance from an array.
     *
     * Factory method that constructs a DocumentData object from a raw array,
     * providing validation and proper type handling. This method is the
     * recommended way to create DocumentData instances from external sources
     * such as JSON payloads or database results.
     *
     * @param array<string, mixed> $data Raw array containing document fields
     *
     * @throws InvalidArgumentException If required 'data' field is missing
     * @return self                     New DocumentData instance
     */
    public static function createFromArray(array $data): self
    {
        return new self(
            data: $data['data'] ?? throw MissingRequiredFieldException::forField('data'),
            included: isset($data['included']) && is_array($data['included']) ? $data['included'] : null,
            errors: isset($data['errors']) && is_array($data['errors']) ? $data['errors'] : null,
            meta: isset($data['meta']) && is_array($data['meta']) ? $data['meta'] : null,
        );
    }

    /**
     * Create a successful response document.
     *
     * Factory method for creating a DocumentData instance representing a
     * successful RPC response. This is the standard way to create success
     * documents without error information.
     *
     * @param  array<string, mixed>                  $data     The successful response payload
     * @param  null|array<int, array<string, mixed>> $included Optional related resources
     * @param  null|array<string, mixed>             $meta     Optional metadata
     * @return self                                  New DocumentData instance for success response
     */
    public static function success(
        array $data,
        ?array $included = null,
        ?array $meta = null,
    ): self {
        return new self(
            data: $data,
            included: $included,
            errors: null,
            meta: $meta,
        );
    }

    /**
     * Create an error response document.
     *
     * Factory method for creating a DocumentData instance representing a
     * failed RPC response. Per JSON:API specification, error responses
     * should not include a data field.
     *
     * @param  array<int, mixed>         $errors Array of error objects
     * @param  null|array<string, mixed> $meta   Optional metadata
     * @return self                      New DocumentData instance for error response
     */
    public static function error(
        array $errors,
        ?array $meta = null,
    ): self {
        return new self(
            data: [],
            included: null,
            errors: $errors,
            meta: $meta,
        );
    }

    /**
     * Check if the document represents an error response.
     *
     * @return bool True if the document contains errors, false otherwise
     */
    public function hasErrors(): bool
    {
        return $this->errors !== null && $this->errors !== [];
    }

    /**
     * Check if the document includes related resources.
     *
     * @return bool True if the document has included resources, false otherwise
     */
    public function hasIncluded(): bool
    {
        return $this->included !== null && $this->included !== [];
    }

    /**
     * Check if the document has metadata.
     *
     * @return bool True if the document has metadata, false otherwise
     */
    public function hasMeta(): bool
    {
        return $this->meta !== null && $this->meta !== [];
    }

    /**
     * Get the number of errors in the document.
     *
     * @return int The count of errors, or 0 if no errors present
     */
    public function getErrorCount(): int
    {
        return $this->errors !== null ? count($this->errors) : 0;
    }

    /**
     * Get the number of included resources in the document.
     *
     * @return int The count of included resources, or 0 if none present
     */
    public function getIncludedCount(): int
    {
        return $this->included !== null ? count($this->included) : 0;
    }
}

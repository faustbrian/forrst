<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Data;

use Cline\Forrst\Exceptions\AbstractRequestException;
use Cline\Forrst\Exceptions\EmptyArrayException;
use Cline\Forrst\Exceptions\EmptyFieldException;
use Cline\Forrst\Exceptions\InvalidArrayElementException;
use Cline\Forrst\Exceptions\InvalidFieldTypeException;
use Cline\Forrst\Exceptions\InvalidFieldValueException;
use Cline\Forrst\Exceptions\MissingRequiredFieldException;
use Cline\Forrst\Exceptions\MutuallyExclusiveFieldsException;
use Override;

use function array_any;
use function array_map;
use function count;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Forrst protocol compliant response object.
 *
 * Encapsulates all components of a Forrst protocol response including protocol
 * version, request identifier, result data or errors, extensions, and metadata.
 * Responses must contain either a result (success) or errors (failure), never both.
 * The id field echoes the request identifier to enable request-response correlation.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/protocol
 * @see https://docs.cline.sh/forrst/document-structure
 */
final readonly class ResponseData extends AbstractData
{
    /**
     * Maximum allowed size for errors array to prevent memory exhaustion.
     */
    private const int MAX_ERRORS_COUNT = 100;

    /**
     * Maximum allowed size for extensions array to prevent memory exhaustion.
     */
    private const int MAX_EXTENSIONS_COUNT = 50;

    /**
     * Maximum allowed depth for nested meta data to prevent stack overflow.
     */
    private const int MAX_META_DEPTH = 10;

    /**
     * Create a new response data instance.
     *
     * @param ProtocolData              $protocol   Forrst protocol identifier object containing protocol name
     *                                              and version. Must match or be compatible with the request
     *                                              protocol version.
     * @param string                    $id         Request identifier echoed from the original request. Must
     *                                              exactly match the request id to enable proper correlation
     *                                              between requests and responses in async scenarios.
     * @param mixed                     $result     The successful result data, typically a resource object,
     *                                              collection, or scalar value. Set to null when errors occur.
     *                                              Must not be present when errors array is populated.
     * @param null|array<ErrorData>     $errors     Array of error objects when request processing fails. Set to
     *                                              null on successful execution. Must contain at least one error
     *                                              when present. Cannot coexist with a non-null result.
     * @param null|array<ExtensionData> $extensions Optional array of extension response data containing results
     *                                              from extension processing (cache status, async job ids, etc.).
     * @param null|array<string, mixed> $meta       Optional metadata about the response such as execution timing,
     *                                              debug information, API version, or other non-standard data
     *                                              that doesn't fit in the standard response structure.
     */
    public function __construct(
        public readonly ProtocolData $protocol,
        public readonly string $id,
        public readonly mixed $result = null,
        public readonly ?array $errors = null,
        public readonly ?array $extensions = null,
        public readonly ?array $meta = null,
    ) {
        // Validate ID is not empty
        if ($id === '') {
            throw EmptyFieldException::forField('id');
        }

        // Validate that result and errors are mutually exclusive
        if ($result !== null && $errors !== null && $errors !== []) {
            throw MutuallyExclusiveFieldsException::forFields('result', 'errors');
        }

        // Validate errors array size and content
        if ($errors !== null) {
            if (count($errors) > self::MAX_ERRORS_COUNT) {
                throw InvalidFieldValueException::forField(
                    'errors',
                    sprintf('cannot exceed %d items', self::MAX_ERRORS_COUNT),
                );
            }

            if ($errors !== [] && $errors === []) {
                throw EmptyArrayException::forField('errors');
            }
        }

        // Validate extensions array size and content
        if ($extensions !== null) {
            if (count($extensions) > self::MAX_EXTENSIONS_COUNT) {
                throw InvalidFieldValueException::forField(
                    'extensions',
                    sprintf('cannot exceed %d items', self::MAX_EXTENSIONS_COUNT),
                );
            }

            foreach ($extensions as $extension) {
                if (!$extension instanceof ExtensionData) {
                    throw InvalidArrayElementException::forField('extensions', 'ExtensionData');
                }
            }
        }

        // Validate meta depth
        if ($meta === null) {
            return;
        }

        $this->validateArrayDepth($meta, self::MAX_META_DEPTH, 'meta');
    }

    /**
     * Create a response from an array following codebase convention.
     *
     * This method follows the codebase standard naming convention for array-based
     * factory methods. It provides validation and proper error handling for malformed data.
     *
     * @param array<string, mixed> $data Response data array to hydrate from
     *
     * @throws InvalidArgumentException If required fields are missing or invalid
     * @return self                     Response instance with validated and hydrated nested objects
     */
    public static function createFromArray(array $data): self
    {
        // Validate required fields
        if (!isset($data['id']) || !is_string($data['id']) || $data['id'] === '') {
            throw MissingRequiredFieldException::forField('id');
        }

        if (!isset($data['protocol'])) {
            throw MissingRequiredFieldException::forField('protocol');
        }

        // Build protocol data
        $protocolData = $data['protocol'];

        if (!is_array($protocolData)) {
            throw InvalidFieldTypeException::forField('protocol', 'array', $protocolData);
        }

        $protocol = ProtocolData::from($protocolData);

        // Validate mutual exclusivity of result and errors
        $hasResult = isset($data['result']);
        $hasErrors = isset($data['errors']) && is_array($data['errors']) && $data['errors'] !== [];

        if ($hasResult && $hasErrors) {
            throw MutuallyExclusiveFieldsException::forFields('result', 'errors');
        }

        // Build errors array
        $errors = null;

        if (isset($data['errors']) && is_array($data['errors'])) {
            if (count($data['errors']) > self::MAX_ERRORS_COUNT) {
                throw InvalidFieldValueException::forField(
                    'errors',
                    sprintf('cannot exceed %d items', self::MAX_ERRORS_COUNT),
                );
            }

            $errors = array_map(
                function (mixed $errorData): ErrorData {
                    if (!is_array($errorData)) {
                        throw InvalidFieldTypeException::forField('errors', 'array', $errorData);
                    }

                    return ErrorData::create($errorData);
                },
                $data['errors'],
            );
        }

        // Build extensions array
        $extensions = null;

        if (isset($data['extensions']) && is_array($data['extensions'])) {
            if (count($data['extensions']) > self::MAX_EXTENSIONS_COUNT) {
                throw InvalidFieldValueException::forField(
                    'extensions',
                    sprintf('cannot exceed %d items', self::MAX_EXTENSIONS_COUNT),
                );
            }

            $extensions = array_map(
                function (mixed $extensionData): ExtensionData {
                    if (!is_array($extensionData)) {
                        throw InvalidFieldTypeException::forField('extensions', 'array', $extensionData);
                    }

                    return ExtensionData::create($extensionData);
                },
                $data['extensions'],
            );
        }

        // Build meta array with depth validation
        $meta = null;

        if (isset($data['meta']) && is_array($data['meta'])) {
            self::validateArrayDepthStatic($data['meta'], self::MAX_META_DEPTH, 'meta');
            $meta = $data['meta'];
        }

        return new self(
            protocol: $protocol,
            id: $data['id'],
            result: $data['result'] ?? null,
            errors: $errors,
            extensions: $extensions,
            meta: $meta,
        );
    }

    /**
     * Create a success response.
     *
     * Factory method for creating a successful response with result data.
     * Sets errors to null and uses standard Forrst protocol identifier.
     *
     * @param mixed                     $result     The result data (resource, collection, or scalar)
     * @param string                    $id         The request identifier echoed from the request
     * @param null|array<ExtensionData> $extensions Optional extension response data
     * @param null|array<string, mixed> $meta       Optional metadata
     *
     * @return self Success response with result populated and errors null
     */
    public static function success(
        mixed $result,
        string $id,
        ?array $extensions = null,
        ?array $meta = null,
    ): self {
        return new self(
            protocol: ProtocolData::forrst(),
            id: $id,
            result: $result,
            errors: null,
            extensions: $extensions,
            meta: $meta,
        );
    }

    /**
     * Create an error response from a request exception.
     *
     * Converts a request exception to a properly formatted error response.
     * The exception's error data is extracted and placed in the errors array.
     *
     * @param AbstractRequestException  $exception  The exception containing error details to convert
     * @param string                    $id         The request identifier echoed from the request
     * @param null|array<ExtensionData> $extensions Optional extension response data
     * @param null|array<string, mixed> $meta       Optional metadata
     *
     * @return self Error response with exception converted to ErrorData
     */
    public static function fromException(
        AbstractRequestException $exception,
        string $id,
        ?array $extensions = null,
        ?array $meta = null,
    ): self {
        return new self(
            protocol: ProtocolData::forrst(),
            id: $id,
            result: null,
            errors: [$exception->toError()],
            extensions: $extensions,
            meta: $meta,
        );
    }

    /**
     * Create an error response with a single error.
     *
     * Factory method for creating an error response with one error object.
     * The error is wrapped in an array per Forrst protocol specification.
     *
     * @param ErrorData                 $error      The error data object to include in response
     * @param string                    $id         The request identifier echoed from the request
     * @param null|array<ExtensionData> $extensions Optional extension response data
     * @param null|array<string, mixed> $meta       Optional metadata
     *
     * @return self Error response with single error in errors array
     */
    public static function error(
        ErrorData $error,
        string $id,
        ?array $extensions = null,
        ?array $meta = null,
    ): self {
        return new self(
            protocol: ProtocolData::forrst(),
            id: $id,
            result: null,
            errors: [$error],
            extensions: $extensions,
            meta: $meta,
        );
    }

    /**
     * Create an error response with multiple errors.
     *
     * Factory method for creating an error response with multiple error objects.
     * Useful when validation produces multiple errors or when aggregating failures.
     *
     * @param array<ErrorData>          $errors     Array of error data objects (must not be empty)
     * @param string                    $id         The request identifier echoed from the request
     * @param null|array<ExtensionData> $extensions Optional extension response data
     * @param null|array<string, mixed> $meta       Optional metadata
     *
     * @return self Error response with multiple errors in errors array
     */
    public static function errors(
        array $errors,
        string $id,
        ?array $extensions = null,
        ?array $meta = null,
    ): self {
        return new self(
            protocol: ProtocolData::forrst(),
            id: $id,
            result: null,
            errors: $errors,
            extensions: $extensions,
            meta: $meta,
        );
    }

    /**
     * Create a response with explicit parameters.
     *
     * Factory method for creating a response with all parameters explicitly provided.
     * This method enforces validation rules and ensures protocol compliance.
     *
     * @param string                    $id         The request identifier
     * @param mixed                     $result     The result data (null if errors present)
     * @param null|array<ErrorData>     $errors     Array of errors (null if successful)
     * @param null|ProtocolData         $protocol   Protocol data (defaults to Forrst protocol)
     * @param null|array<ExtensionData> $extensions Optional extension response data
     * @param null|array<string, mixed> $meta       Optional metadata
     *
     * @throws InvalidArgumentException If parameters are invalid or violate constraints
     * @return self                     Response instance with validated parameters
     */
    public static function createFrom(
        string $id,
        mixed $result = null,
        ?array $errors = null,
        ?ProtocolData $protocol = null,
        ?array $extensions = null,
        ?array $meta = null,
    ): self {
        return new self(
            protocol: $protocol ?? ProtocolData::forrst(),
            id: $id,
            result: $result,
            errors: $errors,
            extensions: $extensions,
            meta: $meta,
        );
    }

    /**
     * Determine the appropriate HTTP status code for this response.
     *
     * Returns 200 OK for successful responses (when result is present). For error
     * responses, returns the error's specific HTTP status code when a single error
     * exists, or 400 Bad Request when multiple errors exist (since HTTP can only
     * return one status code and multiple errors may have different status codes).
     *
     * @return int HTTP status code in the 200-599 range
     */
    public function toStatusCode(): int
    {
        // Success case - result present, no errors
        if ($this->result !== null) {
            return 200;
        }

        // Error case - single error uses its specific status code
        if ($this->errors !== null && $this->errors !== []) {
            // Multiple errors with potentially different status codes
            // Return generic 400 Bad Request since HTTP can only express one status
            if (count($this->errors) > 1) {
                return 400;
            }

            // Single error - use its specific status code
            return $this->errors[0]->toStatusCode();
        }

        // Fallback (should not happen per spec - result and errors are mutually exclusive)
        return 200;
    }

    /**
     * Determine if the request was successful.
     *
     * A response is successful when it contains no errors. Note that a null
     * result with no errors is still considered successful.
     *
     * @return bool True if no errors occurred, false otherwise
     */
    public function isSuccessful(): bool
    {
        return !$this->isFailed();
    }

    /**
     * Determine if the response indicates an error occurred.
     *
     * A response has failed when it contains one or more error objects
     * in the errors array.
     *
     * @return bool True if the response contains error information, false otherwise
     */
    public function isFailed(): bool
    {
        return $this->errors !== null && $this->errors !== [];
    }

    /**
     * Determine if the response indicates a client error occurred.
     *
     * Client errors typically have 4xx status codes and indicate problems with
     * the request such as invalid arguments, missing required fields, or authentication failures.
     *
     * @return bool True if any error is a client-side error, false otherwise
     */
    public function isClientError(): bool
    {
        if ($this->errors === null) {
            return false;
        }

        return array_any($this->errors, fn ($error) => $error->isClient());
    }

    /**
     * Determine if the response indicates a server error occurred.
     *
     * Server errors typically have 5xx status codes and indicate problems with
     * the server such as unhandled exceptions, database failures, or service unavailability.
     *
     * @return bool True if any error is a server-side error, false otherwise
     */
    public function isServerError(): bool
    {
        if ($this->errors === null) {
            return false;
        }

        return array_any($this->errors, fn ($error) => $error->isServer());
    }

    /**
     * Get the first error if present.
     *
     * Convenience method for accessing the first error when you only need
     * to display a single error message to the user.
     *
     * @return null|ErrorData The first error or null if no errors exist
     */
    public function getFirstError(): ?ErrorData
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Get an extension by URN.
     *
     * Searches the extensions array for an extension matching the specified URN.
     * Extensions use URN format like "urn:forrst:ext:async" for identification.
     *
     * @param string $urn The extension URN to search for (e.g., "urn:forrst:ext:cache")
     *
     * @return null|ExtensionData The extension object or null if not found
     */
    public function getExtension(string $urn): ?ExtensionData
    {
        if ($this->extensions === null) {
            return null;
        }

        foreach ($this->extensions as $extension) {
            if ($extension->urn === $urn) {
                return $extension;
            }
        }

        return null;
    }

    /**
     * Check if a specific extension is present in the response.
     *
     * Convenience method that checks if an extension with the given URN
     * exists in the extensions array.
     *
     * @param string $urn The extension URN to check (e.g., "urn:forrst:ext:batch")
     *
     * @return bool True if the extension is present, false otherwise
     */
    public function hasExtension(string $urn): bool
    {
        return $this->getExtension($urn) instanceof ExtensionData;
    }

    /**
     * Convert the response data to an array representation.
     *
     * Per Forrst protocol specification:
     * - Errors always use the `errors` field (plural array)
     * - The `errors` array MUST contain at least one error
     *
     * @return array<string, mixed> The Forrst protocol compliant response array
     */
    #[Override()]
    public function toArray(
        bool $includeSensitive = false,
        array $include = [],
        array $exclude = [],
        array $groups = [],
        array $context = [],
        ?\Cline\Struct\Serialization\SerializationOptions $serialization = null,
    ): array {
        $response = [
            'protocol' => $this->protocol->toArray(),
            'id' => $this->id,
        ];

        if ($this->errors !== null && $this->errors !== []) {
            $response['result'] = null;
            $response['errors'] = array_map(
                fn (ErrorData $err): array => $err->toArray(),
                $this->errors,
            );
        } else {
            $response['result'] = $this->result;
        }

        if ($this->extensions !== null) {
            $response['extensions'] = array_map(
                fn (ExtensionData $ext): array => $ext->toResponseArray(),
                $this->extensions,
            );
        }

        if ($this->meta !== null) {
            $response['meta'] = $this->meta;
        }

        return $response;
    }

    /**
     * Static version of validateArrayDepth for use in static factory methods.
     *
     * @param array<string, mixed> $array     The array to validate
     * @param int                  $maxDepth  Maximum allowed depth
     * @param string               $fieldName Field name for error messages
     * @param int                  $current   Current depth (internal use)
     *
     * @throws InvalidArgumentException If array exceeds maximum depth
     */
    private static function validateArrayDepthStatic(array $array, int $maxDepth, string $fieldName, int $current = 0): void
    {
        if ($current >= $maxDepth) {
            throw InvalidFieldValueException::forField(
                $fieldName,
                sprintf('exceeds maximum depth of %d', $maxDepth),
            );
        }

        foreach ($array as $value) {
            if (!is_array($value)) {
                continue;
            }

            self::validateArrayDepthStatic($value, $maxDepth, $fieldName, $current + 1);
        }
    }

    /**
     * Validate array depth to prevent stack overflow attacks.
     *
     * @param array<string, mixed> $array     The array to validate
     * @param int                  $maxDepth  Maximum allowed depth
     * @param string               $fieldName Field name for error messages
     * @param int                  $current   Current depth (internal use)
     *
     * @throws InvalidArgumentException If array exceeds maximum depth
     */
    private function validateArrayDepth(array $array, int $maxDepth, string $fieldName, int $current = 0): void
    {
        if ($current >= $maxDepth) {
            throw InvalidFieldValueException::forField(
                $fieldName,
                sprintf('exceeds maximum depth of %d', $maxDepth),
            );
        }

        foreach ($array as $value) {
            if (!is_array($value)) {
                continue;
            }

            $this->validateArrayDepth($value, $maxDepth, $fieldName, $current + 1);
        }
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Data;

use Cline\Forrst\Data\Errors\SourceData;
use Cline\Forrst\Enums\ErrorCode;
use Override;

use function is_array;
use function is_int;
use function is_string;

/**
 * Represents a Forrst protocol error response.
 *
 * Encapsulates error information according to the Forrst protocol specification,
 * using the ErrorCode enum for standard codes and supporting custom codes.
 * Each error provides a code, human-readable message, optional source location,
 * and additional structured details.
 *
 * Supports both standard error codes defined in the ErrorCode enum and custom
 * application-specific error codes. Standard codes map to appropriate HTTP
 * status codes and include client/server error classification.
 *
 * Retry semantics are handled by the RetryExtension, not in the error object.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/errors
 */
final readonly class ErrorData extends AbstractData
{
    /**
     * The error code as a string value.
     */
    public readonly string $code;

    /**
     * Create a new Forrst error data instance.
     *
     * @param ErrorCode|string          $code    Error code either as ErrorCode enum instance for
     *                                           standard codes or string for custom application-specific
     *                                           codes. Standard codes are automatically converted to
     *                                           their string values for consistent serialization.
     * @param string                    $message Human-readable error message describing what went
     *                                           wrong. Should be clear and actionable for developers
     *                                           debugging the issue.
     * @param null|SourceData           $source  Optional source location identifying where in the
     *                                           request the error occurred. Uses JSON Pointer for
     *                                           field errors or byte position for parse errors.
     * @param null|array<string, mixed> $details Optional structured data providing additional context
     *                                           about the error. Can include validation failures,
     *                                           constraint violations, or debugging information.
     */
    public function __construct(
        ErrorCode|string $code,
        public readonly string $message,
        public readonly ?SourceData $source = null,
        public readonly ?array $details = null,
    ) {
        $this->code = $code instanceof ErrorCode ? $code->value : $code;
    }

    /**
     * Create from an array representation.
     *
     * Handles deserialization from array data and reconstructs nested
     * SourceData objects from array representation.
     *
     * @param array<string, mixed> $input Array data to deserialize
     *
     * @return static ErrorData instance
     */
    public static function create(array $input): static
    {
        $source = null;

        if (isset($input['source']) && is_array($input['source'])) {
            $sourcePointer = $input['source']['pointer'] ?? null;
            $sourcePosition = $input['source']['position'] ?? null;

            $source = new SourceData(
                pointer: is_string($sourcePointer) ? $sourcePointer : null,
                position: is_int($sourcePosition) ? $sourcePosition : null,
            );
        }

        $code = $input['code'] ?? '';

        if (is_int($code)) {
            $code = (string) $code;
        }

        $message = $input['message'] ?? '';
        $rawDetails = $input['details'] ?? null;

        /** @var null|array<string, mixed> $details */
        $details = is_array($rawDetails) ? $rawDetails : null;

        return new self(
            code: is_string($code) ? $code : '',
            message: is_string($message) ? $message : '',
            source: $source,
            details: $details,
        );
    }

    /**
     * Get the error code as an enum if it's a standard code.
     *
     * Attempts to convert the string error code to an ErrorCode enum instance.
     * Returns null for custom error codes that don't have enum representations.
     *
     * @return null|ErrorCode The enum instance or null for custom codes
     */
    public function getErrorCode(): ?ErrorCode
    {
        return ErrorCode::tryFrom($this->code);
    }

    /**
     * Determine if the error originated from client-side issues.
     *
     * Checks if the error code represents a client error (4xx category).
     * Returns false for custom codes that are not in the ErrorCode enum.
     *
     * @return bool True if the error code indicates a client-side error
     */
    public function isClient(): bool
    {
        $errorCode = $this->getErrorCode();

        if ($errorCode instanceof ErrorCode) {
            return $errorCode->isClient();
        }

        return false;
    }

    /**
     * Determine if the error originated from server-side issues.
     *
     * Checks if the error code represents a server error (5xx category).
     * Returns false for custom codes that are not in the ErrorCode enum.
     *
     * @return bool True if the error code indicates a server-side error
     */
    public function isServer(): bool
    {
        $errorCode = $this->getErrorCode();

        if ($errorCode instanceof ErrorCode) {
            return $errorCode->isServer();
        }

        return false;
    }

    /**
     * Convert the Forrst error code to an appropriate HTTP status code.
     *
     * Maps standard error codes to their corresponding HTTP status codes.
     * For custom codes, returns 500 if the error is server-side, otherwise 400.
     *
     * @return int HTTP status code (400-599)
     */
    public function toStatusCode(): int
    {
        $errorCode = $this->getErrorCode();

        if ($errorCode instanceof ErrorCode) {
            return $errorCode->toStatusCode();
        }

        return $this->isServer() ? 500 : 400;
    }

    /**
     * Convert error data to array representation.
     *
     * Serializes the error to an array suitable for JSON encoding. Only
     * includes source and details if they are non-null, following the
     * Forrst protocol's null omission convention.
     *
     * @return array<string, mixed> Array representation of the error
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
        $result = [
            'code' => $this->code,
            'message' => $this->message,
        ];

        if ($this->source instanceof SourceData) {
            $result['source'] = $this->source->toArray();
        }

        if ($this->details !== null) {
            $result['details'] = $this->details;
        }

        return $result;
    }
}

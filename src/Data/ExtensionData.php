<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Data;

use BackedEnum;
use Cline\Forrst\Exceptions\EmptyFieldException;
use Cline\Forrst\Exceptions\MutuallyExclusiveFieldsException;
use Cline\Forrst\Validation\UrnValidator;

use function is_array;
use function is_string;

/**
 * Represents a Forrst protocol extension in a request or response.
 *
 * Extensions add optional capabilities to Forrst without modifying the core protocol.
 * Each extension is identified by a URN and can include options (request) or data (response).
 *
 * Extensions enable features like async operations, retries, and custom capabilities
 * while maintaining protocol compatibility. Clients declare support for extensions in
 * requests via options; servers acknowledge them in responses via data.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/extensions/
 */
final readonly class ExtensionData
{
    /**
     * The extension URN identifier.
     */
    public string $urn;

    /**
     * Create a new extension data instance.
     *
     * @param BackedEnum|string         $urn     Extension identifier URN using the format
     *                                           "urn:vendor:forrst:ext:name" (e.g., ExtensionUrn::Async,
     *                                           "urn:cline:forrst:ext:retry"). The URN must be globally
     *                                           unique and follow URN naming conventions.
     * @param null|array<string, mixed> $options Extension-specific configuration options sent
     *                                           in requests. Structure varies by extension type.
     *                                           For example, async might include timeout values,
     *                                           retry might include max_attempts.
     * @param null|array<string, mixed> $data    Extension-specific response data sent in
     *                                           responses. Contains extension results or state.
     *                                           For example, async returns operation_id, retry
     *                                           returns attempt counts and timing.
     */
    public function __construct(
        string|BackedEnum $urn,
        public ?array $options = null,
        public ?array $data = null,
    ) {
        if ($urn instanceof BackedEnum) {
            $urnValue = $urn->value;
            $urnString = is_string($urnValue) ? $urnValue : (string) $urnValue;
        } else {
            $urnString = $urn;
        }

        // Validate URN format
        UrnValidator::validateExtensionUrn($urnString, 'urn');

        // Enforce mutual exclusivity
        if ($options !== null && $data !== null) {
            throw MutuallyExclusiveFieldsException::forFields('options', 'data');
        }

        // Validate arrays
        UrnValidator::validateArray($options, 'options');
        UrnValidator::validateArray($data, 'data');

        $this->urn = $urnString;
    }

    /**
     * Create an extension for a request.
     *
     * Factory method for creating request-side extension declarations.
     * The client uses this to indicate support for and configure an extension.
     *
     * @param BackedEnum|string         $urn     Extension URN identifier
     * @param null|array<string, mixed> $options Optional extension configuration
     *
     * @return self ExtensionData instance for request usage
     */
    public static function request(string|BackedEnum $urn, ?array $options = null): self
    {
        return new self(urn: $urn, options: $options);
    }

    /**
     * Create an extension for a response.
     *
     * Factory method for creating response-side extension acknowledgments.
     * The server uses this to acknowledge support and return extension data.
     *
     * @param BackedEnum|string         $urn  Extension URN identifier
     * @param null|array<string, mixed> $data Optional extension response data
     *
     * @return self ExtensionData instance for response usage
     */
    public static function response(string|BackedEnum $urn, ?array $data = null): self
    {
        return new self(urn: $urn, data: $data);
    }

    /**
     * Create from an array representation.
     *
     * Deserializes extension data from array format. Handles both request
     * (with options) and response (with data) formats.
     *
     * @param array<string, mixed> $data Array representation to deserialize
     *
     * @return self ExtensionData instance
     */
    public static function from(array $data): self
    {
        // Validate URN is present and valid
        if (!isset($data['urn']) || !is_string($data['urn']) || $data['urn'] === '') {
            throw EmptyFieldException::forField('urn');
        }

        $urn = $data['urn'];
        $rawOptions = $data['options'] ?? null;
        $rawData = $data['data'] ?? null;

        /** @var null|array<string, mixed> $options */
        $options = is_array($rawOptions) ? $rawOptions : null;

        /** @var null|array<string, mixed> $extensionData */
        $extensionData = is_array($rawData) ? $rawData : null;

        return new self(
            urn: $urn,
            options: $options,
            data: $extensionData,
        );
    }

    /**
     * Convert to array representation for requests.
     *
     * Serializes only the URN and options fields, omitting data.
     * Used when sending extension information in requests.
     *
     * @return array<string, mixed> Request-compatible array
     */
    public function toRequestArray(): array
    {
        $result = ['urn' => $this->urn];

        if ($this->options !== null) {
            $result['options'] = $this->options;
        }

        return $result;
    }

    /**
     * Convert to array representation for responses.
     *
     * Serializes only the URN and data fields, omitting options.
     * Used when sending extension information in responses.
     *
     * @return array<string, mixed> Response-compatible array
     */
    public function toResponseArray(): array
    {
        $result = ['urn' => $this->urn];

        if ($this->data !== null) {
            $result['data'] = $this->data;
        }

        return $result;
    }

    /**
     * Convert to array representation (auto-detect request vs response).
     *
     * Includes both options and data if present. Use toRequestArray or
     * toResponseArray for context-specific serialization.
     *
     * @return array<string, mixed> Complete array representation
     */
    public function toArray(): array
    {
        $result = ['urn' => $this->urn];

        if ($this->options !== null) {
            $result['options'] = $this->options;
        }

        if ($this->data !== null) {
            $result['data'] = $this->data;
        }

        return $result;
    }
}

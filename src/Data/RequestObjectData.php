<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Data;

use BackedEnum;
use Cline\Forrst\Exceptions\InvalidRequestIdException;
use Cline\Forrst\Exceptions\MissingFunctionNameException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Override;

use function array_map;
use function is_array;
use function is_string;

/**
 * Forrst protocol request object representing a complete function invocation.
 *
 * Encapsulates all components of a Forrst request including protocol version,
 * unique request identifier, call details (function name, version, arguments),
 * and optional context and extensions. This is the primary data structure
 * transmitted from client to server in the Forrst protocol.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/protocol
 * @see https://docs.cline.sh/forrst/document-structure
 */
final readonly class RequestObjectData extends AbstractData
{
    /**
     * Create a new Forrst request object instance.
     *
     * @param ProtocolData              $protocol   Forrst protocol identifier object containing the protocol
     *                                              name and version. Used to ensure protocol compatibility
     *                                              and enable version-specific processing behavior.
     * @param string                    $id         Unique request identifier for correlating requests with their
     *                                              corresponding responses. Must be unique per request within a session.
     *                                              The server echoes this identifier in the response to enable request
     *                                              matching in asynchronous or multiplexed communication scenarios.
     * @param CallData                  $call       The call object containing function name, optional version specifier,
     *                                              and function arguments. This represents the actual function invocation
     *                                              details that the server will execute.
     * @param array<string, mixed>      $context    Optional context data providing request-scoped information such as
     *                                              authentication credentials, distributed tracing identifiers, tenant
     *                                              information, or other metadata required for request processing.
     * @param array<ExtensionData>      $extensions Optional array of extension configurations that modify request
     *                                              processing behavior. Extensions enable features like async execution,
     *                                              batch processing, caching, or custom protocol enhancements.
     */
    public function __construct(
        public readonly ProtocolData $protocol,
        public readonly string $id,
        public readonly CallData $call,
        public readonly array $context = [],
        public readonly array $extensions = [],
        public readonly array $meta = [],
    ) {}

    /**
     * Create a RequestObjectData from an array.
     *
     * Hydrates a request object from an associative array, typically from
     * JSON-decoded request data. Validates required fields and ensures the
     * request includes a valid identifier and function name before hydration.
     *
     * @param array<string, mixed> $input Data array to create from
     *
     * @throws InvalidRequestIdException    If id field is missing or invalid
     * @throws MissingFunctionNameException If call.function field is missing
     * @return static                       Configured request object
     */
    public static function create(array $input): static
    {
        if (!isset($input['id']) || !is_string($input['id'])) {
            throw InvalidRequestIdException::create();
        }

        $call = $input['call'] ?? null;

        if ($call instanceof CallData) {
            return parent::create($input);
        }

        if (!is_array($call)) {
            throw MissingFunctionNameException::create();
        }

        if (!isset($call['function']) || !is_string($call['function'])) {
            throw MissingFunctionNameException::create();
        }

        return parent::create($input);
    }

    /**
     * Create a standard Forrst request.
     *
     * Factory method for creating request objects with automatically generated
     * identifiers. Generates a ULID identifier if none is provided.
     *
     * @param  string                    $function   Name of the function to invoke
     * @param  null|array<string, mixed> $arguments  Optional function arguments
     * @param  null|string               $version    Optional function version
     * @param  null|string               $id         Optional custom request identifier (ULID generated if null)
     * @param  null|array<string, mixed> $context    Optional context data
     * @param  null|array<ExtensionData> $extensions Optional extensions to invoke
     * @return self                      Configured request object ready for transmission
     */
    public static function asRequest(
        string $function,
        ?array $arguments = null,
        ?string $version = null,
        ?string $id = null,
        ?array $context = null,
        ?array $extensions = null,
    ): self {
        return new self(
            protocol: ProtocolData::forrst(),
            id: $id ?? Str::ulid()->toString(),
            call: new CallData(
                function: $function,
                version: $version,
                arguments: $arguments,
            ),
            context: $context ?? [],
            extensions: $extensions ?? [],
        );
    }

    /**
     * Retrieve runtime metadata for extensions.
     *
     * This metadata is maintained on the request object for internal
     * processing and is not serialized onto the wire payload.
     *
     * @return array<string, mixed> Extension metadata for the current request
     */
    public function meta(): array
    {
        return $this->meta;
    }

    /**
     * Retrieve a specific metadata value using dot notation.
     *
     * @param  string $key     Metadata key in dot notation
     * @param  mixed  $default Default value to return if not found
     * @return mixed  The metadata value or the default value
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->meta(), $key, $default);
    }

    /**
     * Replace the entire runtime metadata payload.
     *
     * @param  array<string, mixed> $meta Metadata to attach to the request
     * @return self                  New request instance with updated metadata
     */
    public function withMeta(array $meta): self
    {
        return $this->with(meta: $meta);
    }

    /**
     * Replace a single metadata value using dot notation.
     *
     * @param  string $key   Metadata key in dot notation
     * @param  mixed  $value Metadata value to set
     * @return self          New request instance with updated metadata
     */
    public function withMetaValue(string $key, mixed $value): self
    {
        $meta = $this->meta();

        Arr::set($meta, $key, $value);

        return $this->withMeta($meta);
    }

    /**
     * Retrieve a specific argument value using dot notation.
     *
     * Provides convenient access to nested argument values using Laravel's
     * dot notation syntax (e.g., "user.email" for nested structures).
     *
     * @param  string $key     Argument key in dot notation
     * @param  mixed  $default Default value to return if argument is not found
     * @return mixed  The argument value or the default value
     */
    public function getArgument(string $key, mixed $default = null): mixed
    {
        if ($this->call->arguments === null) {
            return $default;
        }

        return Arr::get($this->call->arguments, $key, $default);
    }

    /**
     * Retrieve all arguments as an array.
     *
     * @return null|array<string, mixed> Complete arguments array or null
     */
    public function getArguments(): ?array
    {
        return $this->call->arguments;
    }

    /**
     * Get the function name being called.
     *
     * @return string The function name
     */
    public function getFunction(): string
    {
        return $this->call->function;
    }

    /**
     * Get the function version if specified.
     *
     * @return null|string The function version or null
     */
    public function getVersion(): ?string
    {
        return $this->call->version;
    }

    /**
     * Retrieve a specific context value using dot notation.
     *
     * @param  string $key     Context key in dot notation
     * @param  mixed  $default Default value to return if not found
     * @return mixed  The context value or the default value
     */
    public function getContext(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->context, $key, $default);
    }

    /**
     * Get an extension by URN.
     *
     * @param  BackedEnum|string  $urn The extension URN (e.g., ExtensionUrn::Async or "urn:forrst:ext:async")
     * @return null|ExtensionData The extension or null if not found
     */
    public function getExtension(string|BackedEnum $urn): ?ExtensionData
    {
        $urn = $urn instanceof BackedEnum ? $urn->value : $urn;

        foreach ($this->extensions as $extension) {
            if ($extension->urn === $urn) {
                return $extension;
            }
        }

        return null;
    }

    /**
     * Check if a specific extension is requested.
     *
     * @param  BackedEnum|string $urn The extension URN to check
     * @return bool              True if the extension is present
     */
    public function hasExtension(string|BackedEnum $urn): bool
    {
        return $this->getExtension($urn) instanceof ExtensionData;
    }

    /**
     * Get extension options by URN.
     *
     * @param  BackedEnum|string $urn     The extension URN
     * @param  string            $key     Option key in dot notation
     * @param  mixed             $default Default value if not found
     * @return mixed             The option value or default
     */
    public function getExtensionOption(string|BackedEnum $urn, string $key, mixed $default = null): mixed
    {
        $extension = $this->getExtension($urn);

        if (!$extension instanceof ExtensionData || $extension->options === null) {
            return $default;
        }

        return Arr::get($extension->options, $key, $default);
    }

    /**
     * Convert to array representation.
     *
     * Serializes the request object to an associative array suitable for JSON encoding
     * and transmission. Omits optional fields that are null to minimize payload size.
     *
     * @return array<string, mixed> Request data as Forrst protocol compliant associative array
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
            'protocol' => $this->protocol->toArray(),
            'id' => $this->id,
            'call' => [
                'function' => $this->call->function,
            ],
        ];

        if ($this->call->version !== null) {
            $result['call']['version'] = $this->call->version;
        }

        if ($this->call->arguments !== null) {
            $result['call']['arguments'] = $this->call->arguments;
        }

        if ($this->context !== []) {
            $result['context'] = $this->context;
        }

        if ($this->extensions !== []) {
            $result['extensions'] = array_map(
                fn (ExtensionData $ext): array => $ext->toRequestArray(),
                $this->extensions,
            );
        }

        return $result;
    }
}

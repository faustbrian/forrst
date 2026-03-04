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
final class RequestObjectData extends AbstractData
{
    /**
     * Runtime metadata storage for extensions.
     *
     * Non-readonly to allow extensions to store per-request state during
     * processing. This is NOT serialized or transmitted - only for internal use.
     *
     * @var array<string, mixed>
     */
    public array $meta = [];

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
     * @param null|array<string, mixed> $context    Optional context data providing request-scoped information such as
     *                                              authentication credentials, distributed tracing identifiers, tenant
     *                                              information, or other metadata required for request processing.
     * @param null|array<ExtensionData> $extensions Optional array of extension configurations that modify request
     *                                              processing behavior. Extensions enable features like async execution,
     *                                              batch processing, caching, or custom protocol enhancements.
     */
    public function __construct(
        public readonly ProtocolData $protocol,
        public readonly string $id,
        public readonly CallData $call,
        public readonly ?array $context = null,
        public readonly ?array $extensions = null,
    ) {}

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
            context: $context,
            extensions: $extensions,
        );
    }

    /**
     * Create a RequestObjectData from an array.
     *
     * Hydrates a request object from an associative array, typically from JSON-decoded
     * request data. Validates required fields and constructs nested data objects.
     *
     * @param array<string, mixed> ...$payloads Data arrays to create from (only first element used)
     *
     * @throws InvalidRequestIdException    If id field is missing or invalid
     * @throws MissingFunctionNameException If call.function field is missing
     * @return static                       Configured request object
     */
    #[Override()]
    public static function from(mixed ...$payloads): static
    {
        $rawData = $payloads[0] ?? [];

        /** @var array<string, mixed> $data */
        $data = $rawData;

        // Protocol must be an object with name and version
        $protocolData = $data['protocol'] ?? [];

        /** @var array{name?: string, version?: string} $protocolArray */
        $protocolArray = is_array($protocolData) ? $protocolData : [];

        $protocol = ProtocolData::from($protocolArray);

        // ID is required
        if (!isset($data['id']) || !is_string($data['id'])) {
            throw InvalidRequestIdException::create();
        }

        // Call is required - can be CallData object or array
        $callData = $data['call'] ?? [];

        if ($callData instanceof CallData) {
            $call = $callData;
        } elseif (is_array($callData)) {
            $rawFunctionName = $callData['function'] ?? null;

            if (!is_string($rawFunctionName)) {
                throw MissingFunctionNameException::create();
            }

            $rawVersion = $callData['version'] ?? null;
            $rawArguments = $callData['arguments'] ?? null;

            /** @var null|array<string, mixed> $arguments */
            $arguments = is_array($rawArguments) ? $rawArguments : null;

            $call = new CallData(
                function: $rawFunctionName,
                version: is_string($rawVersion) ? $rawVersion : null,
                arguments: $arguments,
            );
        } else {
            throw MissingFunctionNameException::create();
        }

        // Hydrate extensions array
        $extensions = null;

        if (isset($data['extensions']) && is_array($data['extensions'])) {
            /** @var array<ExtensionData> $extensions */
            $extensions = array_map(
                function (mixed $extensionData): ExtensionData {
                    /** @var array<string, mixed> $extensionArray */
                    $extensionArray = is_array($extensionData) ? $extensionData : [];

                    return ExtensionData::from($extensionArray);
                },
                $data['extensions'],
            );
        }

        $rawContext = $data['context'] ?? null;

        /** @var null|array<string, mixed> $context */
        $context = is_array($rawContext) ? $rawContext : null;

        return new self(
            protocol: $protocol,
            id: $data['id'],
            call: $call,
            context: $context,
            extensions: $extensions,
        );
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
        if ($this->context === null) {
            return $default;
        }

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
    public function toArray(): array
    {
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

        if ($this->context !== null) {
            $result['context'] = $this->context;
        }

        if ($this->extensions !== null) {
            $result['extensions'] = array_map(
                fn (ExtensionData $ext): array => $ext->toRequestArray(),
                $this->extensions,
            );
        }

        return $result;
    }
}

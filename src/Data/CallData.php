<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Data;

use Cline\Forrst\Exceptions\MissingRequiredFieldException;

use function count;
use function is_array;

/**
 * Represents the call object within a Forrst protocol request.
 *
 * Contains the function to invoke, optional version for per-function versioning,
 * and the arguments to pass to the function. This is the core payload structure
 * that identifies what RPC method should be executed and with what parameters.
 *
 * The function name uses URN format (e.g., "urn:vendor:forrst:fn:users:create",
 * "urn:vendor:forrst:fn:orders:list"). Version allows for per-function API
 * versioning independent of the server version.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/protocol
 */
final class CallData extends AbstractData
{
    /**
     * Create a new call data instance.
     *
     * @param string                    $function  The function URN to invoke (e.g.,
     *                                             "urn:vendor:forrst:fn:orders:create",
     *                                             "urn:vendor:forrst:fn:users:update"). Must match a
     *                                             registered function URN in the server's function registry.
     * @param null|string               $version   Optional function version for per-function API
     *                                             versioning. Allows different versions of the same
     *                                             function to coexist (e.g., "1.0", "2.0"). If null,
     *                                             the server's default version is used.
     * @param null|array<string, mixed> $arguments Optional named arguments to pass to the function.
     *                                             Structure must match the function's parameter schema.
     *                                             Arguments are validated against the function's input
     *                                             specification before execution.
     */
    public function __construct(
        public readonly string $function,
        public readonly ?string $version = null,
        public readonly ?array $arguments = null,
    ) {}

    /**
     * Create a call data instance from an array.
     *
     * @param  array<string, mixed>          $data The array data containing call information
     * @throws MissingRequiredFieldException If function name is missing
     * @return self                          Configured CallData instance
     */
    public static function createFromArray(array $data): self
    {
        return new self(
            function: $data['function'] ?? throw MissingRequiredFieldException::forField('function'),
            version: $data['version'] ?? null,
            arguments: isset($data['arguments']) && is_array($data['arguments']) ? $data['arguments'] : null,
        );
    }

    /**
     * Create a call data instance from explicit parameters.
     *
     * @param  string                    $function  The function name
     * @param  null|array<string, mixed> $arguments Optional arguments
     * @param  null|string               $version   Optional version
     * @return self                      Configured CallData instance
     */
    public static function createFrom(
        string $function,
        ?array $arguments = null,
        ?string $version = null,
    ): self {
        return new self(
            function: $function,
            version: $version,
            arguments: $arguments,
        );
    }

    /**
     * Check if the call has any arguments.
     *
     * @return bool True if arguments are present
     */
    public function hasArguments(): bool
    {
        return $this->arguments !== null && $this->arguments !== [];
    }

    /**
     * Get the number of arguments.
     *
     * @return int The argument count
     */
    public function getArgumentCount(): int
    {
        return $this->arguments !== null ? count($this->arguments) : 0;
    }

    /**
     * Check if a specific argument exists.
     *
     * @param  string $key The argument key
     * @return bool   True if the argument exists
     */
    public function hasArgument(string $key): bool
    {
        return isset($this->arguments[$key]);
    }

    /**
     * Get a specific argument value with optional default.
     *
     * @param  string $key     The argument key
     * @param  mixed  $default The default value if not found
     * @return mixed  The argument value or default
     */
    public function getArgument(string $key, mixed $default = null): mixed
    {
        return $this->arguments[$key] ?? $default;
    }

    /**
     * Check if a version was specified.
     *
     * @return bool True if version is set
     */
    public function hasVersion(): bool
    {
        return $this->version !== null;
    }
}

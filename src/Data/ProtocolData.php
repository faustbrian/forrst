<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Data;

use Stringable;

use function sprintf;

/**
 * Forrst protocol identifier containing name and version information.
 *
 * Encapsulates the protocol identifier used in all Forrst requests and responses
 * to ensure protocol version compatibility between clients and servers. The protocol
 * field appears in both request and response objects as the first field, allowing
 * parsers to immediately determine protocol version before processing the message.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/protocol
 * @see https://docs.cline.sh/forrst/document-structure
 * @psalm-immutable
 */
final readonly class ProtocolData implements Stringable
{
    public const string NAME = 'forrst';

    public const string VERSION = '0.1.0';

    /**
     * Create a new protocol data instance.
     *
     * @param string $name    The protocol name identifier. Defaults to 'forrst' for standard
     *                        Forrst protocol messages. Custom protocols may use different names
     *                        for extension or derivative protocol implementations.
     * @param string $version The protocol version number in semantic versioning format (e.g., '0.1.0').
     *                        Used to ensure compatibility between client and server implementations
     *                        and to enable version-specific behavior or error handling.
     */
    public function __construct(
        public string $name = self::NAME,
        public string $version = self::VERSION,
    ) {}

    /**
     * Get string representation for debugging.
     *
     * Returns a human-readable string in format "name/version" for logging,
     * debugging, and error messages.
     *
     * @return string Protocol identifier in format "forrst/0.1.0"
     */
    public function __toString(): string
    {
        return sprintf('%s/%s', $this->name, $this->version);
    }

    /**
     * Create the default Forrst protocol instance.
     *
     * Factory method that returns a protocol instance with the standard Forrst
     * name and current version. This is the recommended way to create protocol
     * identifiers for standard Forrst requests and responses.
     *
     * @return self Protocol instance with name='forrst' and version='0.1.0'
     */
    public static function forrst(): self
    {
        return new self(self::NAME, self::VERSION);
    }

    /**
     * Create from an array representation.
     *
     * Hydrates a protocol instance from an associative array, typically received
     * from JSON-decoded request or response data. Missing fields default to standard
     * Forrst protocol values.
     *
     * @param array{name?: string, version?: string} $data Associative array with optional name and version keys
     *
     * @return self Hydrated protocol instance
     */
    public static function from(array $data): self
    {
        return new self(
            name: $data['name'] ?? self::NAME,
            version: $data['version'] ?? self::VERSION,
        );
    }

    /**
     * Convert to array representation.
     *
     * Serializes the protocol data to an associative array suitable for JSON encoding
     * in request and response messages. The structure matches the Forrst protocol specification.
     *
     * @return array{name: string, version: string} Protocol data as associative array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
        ];
    }

    /**
     * Check if this protocol matches expected Forrst protocol.
     *
     * Validates that the protocol name is 'forrst', useful for protocol detection
     * and validation when handling messages that might use different protocols.
     *
     * @return bool True if protocol name is 'forrst', false otherwise
     */
    public function isForrst(): bool
    {
        return $this->name === self::NAME;
    }
}

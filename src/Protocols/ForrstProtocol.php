<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Protocols;

use Cline\Forrst\Contracts\ProtocolInterface;
use InvalidArgumentException;
use JsonException;

use const JSON_THROW_ON_ERROR;

use function assert;
use function is_array;
use function json_decode;
use function json_encode;

/**
 * JSON-based protocol implementation for Forrst RPC communication.
 *
 * Handles encoding and decoding of Forrst protocol messages in JSON format.
 * All messages follow the structure defined in the Forrst protocol specification,
 * with required protocol metadata (name and version) and request/response payloads.
 *
 * Message formats:
 * - Request: {"protocol":{"name":"forrst","version":"0.1.0"},"id":"...","call":{"function":"...","arguments":{...}}}
 * - Success: {"protocol":{"name":"forrst","version":"0.1.0"},"id":"...","result":{...}}
 * - Error: {"protocol":{"name":"forrst","version":"0.1.0"},"id":"...","result":null,"errors":[...]}
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/protocol
 *
 * @psalm-immutable
 */
final readonly class ForrstProtocol implements ProtocolInterface
{
    /**
     * Current Forrst protocol version following semantic versioning.
     */
    public const string VERSION = '0.1.0';

    /**
     * Encodes a request payload into a JSON string.
     *
     * @param array<string, mixed> $data Request data structure to encode
     *
     * @throws JsonException When JSON encoding fails
     *
     * @return string JSON-encoded request string
     */
    public function encodeRequest(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    /**
     * Encodes a response payload into a JSON string.
     *
     * @param array<string, mixed> $data Response data structure to encode
     *
     * @throws JsonException When JSON encoding fails
     *
     * @return string JSON-encoded response string
     */
    public function encodeResponse(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    /**
     * Decodes a JSON request string into an array.
     *
     * @param string $data JSON-encoded request string
     *
     * @throws JsonException When JSON decoding fails or depth limit is exceeded
     *
     * @return array<string, mixed> Decoded request data structure
     */
    public function decodeRequest(string $data): array
    {
        $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($decoded));

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Decodes a JSON response string into an array.
     *
     * @param string $data JSON-encoded response string
     *
     * @throws JsonException When JSON decoding fails or depth limit is exceeded
     *
     * @return array<string, mixed> Decoded response data structure
     */
    public function decodeResponse(string $data): array
    {
        $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($decoded));

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Returns the MIME content type for Forrst protocol messages.
     *
     * @return string Content type header value
     */
    public function getContentType(): string
    {
        return 'application/json';
    }

    /**
     * Validate that data structure conforms to protocol requirements.
     *
     * @param array<string, mixed> $data Data to validate
     *
     * @throws InvalidArgumentException If data is invalid
     */
    public function validate(array $data): void
    {
        // Protocol validation is handled elsewhere in the request pipeline
        // This method exists for interface compliance
    }
}

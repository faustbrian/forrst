<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Contracts;

use InvalidArgumentException;
use JsonException;

/**
 * Forrst protocol contract interface.
 *
 * Defines the contract for implementing RPC protocol handlers that transform
 * between internal data representations and wire format. Protocols encapsulate
 * serialization, message structure, and content negotiation for RPC communication.
 *
 * Protocols handle the complete message format transformation between internal
 * representation and wire format (Forrst protocol, XML-RPC, etc.). Each protocol
 * defines its own request/response structure, serialization rules, and content type.
 *
 * Unlike simple serializers, protocols transform between different message structures:
 * - Forrst: {"jsonrpc":"2.0","method":"foo","params":[1,2],"id":1}
 * - XML-RPC: <methodCall><methodName>foo</methodName><params>...</params></methodCall>
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/protocol Protocol specification
 */
interface ProtocolInterface
{
    /**
     * Encode internal request/response data to protocol-specific wire format.
     *
     * Transforms internal representation to protocol's message structure and serializes.
     *
     * @param array<string, mixed> $data Internal RPC data structure
     *
     * @throws JsonException When encoding fails
     *
     * @return string Protocol-specific wire format
     */
    public function encodeRequest(array $data): string;

    /**
     * Encode response data to protocol-specific wire format.
     *
     * @param array<string, mixed> $data Internal response data structure
     *
     * @throws JsonException When encoding fails
     *
     * @return string Protocol-specific response format
     */
    public function encodeResponse(array $data): string;

    /**
     * Decode protocol-specific wire format to internal request representation.
     *
     * Parses protocol message and transforms to internal RPC structure.
     *
     * @param string $data Protocol-specific request string
     *
     * @throws JsonException When decoding fails
     *
     * @return array<string, mixed> Internal RPC request structure
     */
    public function decodeRequest(string $data): array;

    /**
     * Decode protocol-specific response to internal representation.
     *
     * @param string $data Protocol-specific response string
     *
     * @throws JsonException When decoding fails
     *
     * @return array<string, mixed> Internal response structure
     */
    public function decodeResponse(string $data): array;

    /**
     * Get the HTTP Content-Type header value for this protocol.
     *
     * @return string Content-Type header value (e.g., 'application/json', 'text/xml')
     */
    public function getContentType(): string;

    /**
     * Validate that data structure conforms to protocol requirements.
     *
     * @param array<string, mixed> $data Data to validate
     *
     * @throws InvalidArgumentException If data is invalid
     */
    public function validate(array $data): void;
}

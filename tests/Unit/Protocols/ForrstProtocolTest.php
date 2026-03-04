<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Protocols\ForrstProtocol;

describe('ForrstProtocol', function (): void {
    beforeEach(function (): void {
        $this->protocol = new ForrstProtocol();
    });

    describe('Happy Paths', function (): void {
        test('encodes array to JSON string', function (): void {
            // Arrange
            $data = [
                'jsonrpc' => '2.0',
                'method' => 'test.method',
                'params' => ['param1' => 'value1'],
                'id' => 1,
            ];

            // Act
            $result = $this->protocol->encodeRequest($data);

            // Assert
            expect($result)->toBeString();
            expect(json_decode($result, true))->toBe($data);
        });

        test('encodes response to JSON string', function (): void {
            // Arrange
            $data = [
                'jsonrpc' => '2.0',
                'result' => ['value' => 'success'],
                'id' => 1,
            ];

            // Act
            $result = $this->protocol->encodeResponse($data);

            // Assert
            expect($result)->toBeString();
            expect(json_decode($result, true))->toBe($data);
        });

        test('decodes JSON string to array', function (): void {
            // Arrange
            $json = '{"jsonrpc":"2.0","method":"test.method","params":{"param1":"value1"},"id":1}';

            // Act
            $result = $this->protocol->decodeRequest($json);

            // Assert
            expect($result)->toBeArray();
            expect($result)->toBe([
                'jsonrpc' => '2.0',
                'method' => 'test.method',
                'params' => ['param1' => 'value1'],
                'id' => 1,
            ]);
        });

        test('returns correct content type', function (): void {
            // Act
            $contentType = $this->protocol->getContentType();

            // Assert
            expect($contentType)->toBe('application/json');
        });

        test('handles empty array', function (): void {
            // Arrange
            $data = [];

            // Act
            $result = $this->protocol->encodeRequest($data);

            // Assert
            expect($result)->toBe('[]');
            expect($this->protocol->decodeRequest($result))->toBe([]);
        });

        test('handles nested arrays', function (): void {
            // Arrange
            $data = [
                'jsonrpc' => '2.0',
                'result' => [
                    'users' => [
                        ['id' => 1, 'name' => 'Alice'],
                        ['id' => 2, 'name' => 'Bob'],
                    ],
                ],
                'id' => 1,
            ];

            // Act
            $encoded = $this->protocol->encodeRequest($data);
            $decoded = $this->protocol->decodeRequest($encoded);

            // Assert
            expect($decoded)->toBe($data);
        });

        test('handles null values', function (): void {
            // Arrange
            $data = [
                'jsonrpc' => '2.0',
                'result' => null,
                'id' => 1,
            ];

            // Act
            $encoded = $this->protocol->encodeRequest($data);
            $decoded = $this->protocol->decodeRequest($encoded);

            // Assert
            expect($decoded)->toBe($data);
        });

        test('handles boolean values', function (): void {
            // Arrange
            $data = [
                'jsonrpc' => '2.0',
                'result' => ['success' => true, 'failed' => false],
                'id' => 1,
            ];

            // Act
            $encoded = $this->protocol->encodeRequest($data);
            $decoded = $this->protocol->decodeRequest($encoded);

            // Assert
            expect($decoded)->toBe($data);
        });

        test('handles numeric values', function (): void {
            // Arrange
            $data = [
                'jsonrpc' => '2.0',
                'result' => ['int' => 42, 'float' => 3.14],
                'id' => 1,
            ];

            // Act
            $encoded = $this->protocol->encodeRequest($data);
            $decoded = $this->protocol->decodeRequest($encoded);

            // Assert
            expect($decoded)->toBe($data);
        });

        test('preserves unicode characters', function (): void {
            // Arrange
            $data = [
                'jsonrpc' => '2.0',
                'result' => ['message' => '你好世界 🌍'],
                'id' => 1,
            ];

            // Act
            $encoded = $this->protocol->encodeRequest($data);
            $decoded = $this->protocol->decodeRequest($encoded);

            // Assert
            expect($decoded)->toBe($data);
        });
    });

    describe('Sad Paths', function (): void {
        test('throws exception on invalid JSON during decode', function (): void {
            // Arrange
            $invalidJson = '{invalid json}';

            // Act & Assert
            expect(fn (): mixed => $this->protocol->decodeRequest($invalidJson))
                ->toThrow(JsonException::class);
        });

        test('throws exception on malformed JSON string', function (): void {
            // Arrange
            $malformedJson = '{"key": "value"';

            // Act & Assert
            expect(fn (): mixed => $this->protocol->decodeRequest($malformedJson))
                ->toThrow(JsonException::class);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles deeply nested structures', function (): void {
            // Arrange
            $data = [
                'level1' => [
                    'level2' => [
                        'level3' => [
                            'level4' => [
                                'value' => 'deep',
                            ],
                        ],
                    ],
                ],
            ];

            // Act
            $encoded = $this->protocol->encodeRequest($data);
            $decoded = $this->protocol->decodeRequest($encoded);

            // Assert
            expect($decoded)->toBe($data);
        });

        test('handles array with numeric keys', function (): void {
            // Arrange
            $data = [1, 2, 3, 4, 5];

            // Act
            $encoded = $this->protocol->encodeRequest($data);
            $decoded = $this->protocol->decodeRequest($encoded);

            // Assert
            expect($decoded)->toBe($data);
        });

        test('handles mixed arrays', function (): void {
            // Arrange
            $data = [
                'string' => 'text',
                'number' => 123,
                'bool' => true,
                'null' => null,
                'array' => [1, 2, 3],
                'object' => ['key' => 'value'],
            ];

            // Act
            $encoded = $this->protocol->encodeRequest($data);
            $decoded = $this->protocol->decodeRequest($encoded);

            // Assert
            expect($decoded)->toBe($data);
        });

        test('handles empty string in decode', function (): void {
            // Arrange
            $emptyString = '';

            // Act & Assert
            expect(fn (): mixed => $this->protocol->decodeRequest($emptyString))
                ->toThrow(JsonException::class);
        });

        test('encodes preserves order of keys', function (): void {
            // Arrange
            $data = [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'test',
                'params' => [],
            ];

            // Act
            $encoded = $this->protocol->encodeRequest($data);

            // Assert
            // JSON should maintain key order when re-parsed
            expect(json_decode($encoded, true))->toBe($data);
        });

        test('handles special characters in strings', function (): void {
            // Arrange
            $data = [
                'special' => "Line 1\nLine 2\tTabbed\r\nWindows",
                'quotes' => 'He said "Hello"',
                'backslash' => 'C:\\path\\to\\file',
            ];

            // Act
            $encoded = $this->protocol->encodeRequest($data);
            $decoded = $this->protocol->decodeRequest($encoded);

            // Assert
            expect($decoded)->toBe($data);
        });

        test('decodes response JSON string to array', function (): void {
            // Arrange
            $json = '{"jsonrpc":"2.0","result":"success","id":1}';

            // Act
            $result = $this->protocol->decodeResponse($json);

            // Assert
            expect($result)->toBeArray();
            expect($result)->toBe([
                'jsonrpc' => '2.0',
                'result' => 'success',
                'id' => 1,
            ]);
        });
    });

    describe('Protocol Version', function (): void {
        test('returns correct Forrst protocol version constant', function (): void {
            // Assert
            expect(ForrstProtocol::VERSION)->toBe('0.1.0');
        });
    });
});

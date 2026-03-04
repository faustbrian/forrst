<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\CallData;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Exceptions\InvalidRequestIdException;
use Cline\Forrst\Exceptions\MissingFunctionNameException;

describe('RequestObjectData', function (): void {
    describe('Happy Paths', function (): void {
        test('creates request object with all parameters', function (): void {
            // Arrange
            $call = new CallData(
                function: 'urn:cline:forrst:fn:user:create',
                version: '1.0.0',
                arguments: ['name' => 'John Doe', 'email' => 'john@example.com'],
            );

            // Act
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'test-123',
                call: $call,
                context: ['tenant' => 'acme'],
            );

            // Assert
            expect($request)->toBeInstanceOf(RequestObjectData::class)
                ->and($request->protocol)->toBeInstanceOf(ProtocolData::class)
                ->and($request->protocol->name)->toBe('forrst')
                ->and($request->protocol->version)->toBe('0.1.0')
                ->and($request->id)->toBe('test-123')
                ->and($request->call->function)->toBe('urn:cline:forrst:fn:user:create')
                ->and($request->call->version)->toBe('1.0.0')
                ->and($request->call->arguments)->toBe(['name' => 'John Doe', 'email' => 'john@example.com'])
                ->and($request->context)->toBe(['tenant' => 'acme']);
        });

        test('creates request using asRequest factory with auto-generated ID', function (): void {
            // Arrange
            $function = 'account.getBalance';
            $arguments = ['account_id' => 12_345];

            // Act
            $request = RequestObjectData::asRequest($function, $arguments);

            // Assert
            expect($request)->toBeInstanceOf(RequestObjectData::class)
                ->and($request->protocol)->toBeInstanceOf(ProtocolData::class)
                ->and($request->id)->toBeString()
                ->and($request->id)->toMatch('/^[0-9A-Z]{26}$/i') // ULID pattern
                ->and($request->getFunction())->toBe('account.getBalance')
                ->and($request->getArguments())->toBe(['account_id' => 12_345]);
        });

        test('creates request using asRequest factory with custom ID', function (): void {
            // Arrange
            $function = 'product.update';
            $arguments = ['product_id' => 999, 'price' => 29.99];
            $customId = 'custom-request-456';

            // Act
            $request = RequestObjectData::asRequest($function, $arguments, null, $customId);

            // Assert
            expect($request)->toBeInstanceOf(RequestObjectData::class)
                ->and($request->protocol)->toBeInstanceOf(ProtocolData::class)
                ->and($request->id)->toBe('custom-request-456')
                ->and($request->getFunction())->toBe('product.update')
                ->and($request->getArguments())->toBe(['product_id' => 999, 'price' => 29.99]);
        });

        test('creates request using asRequest factory with version', function (): void {
            // Act
            $request = RequestObjectData::asRequest('orders.list', null, '2');

            // Assert
            expect($request->getVersion())->toBe('2');
        });

        test('retrieves specific argument using dot notation', function (): void {
            // Arrange
            $arguments = [
                'user' => [
                    'profile' => [
                        'email' => 'test@example.com',
                        'age' => 25,
                    ],
                ],
            ];
            $call = new CallData('test', null, $arguments);
            $request = new RequestObjectData(ProtocolData::forrst(), 'req-1', $call);

            // Act
            $email = $request->getArgument('user.profile.email');
            $age = $request->getArgument('user.profile.age');

            // Assert
            expect($email)->toBe('test@example.com')
                ->and($age)->toBe(25);
        });

        test('retrieves all arguments using getArguments method', function (): void {
            // Arrange
            $arguments = ['key1' => 'value1', 'key2' => 'value2', 'key3' => ['nested' => 'value']];
            $call = new CallData('test.method', null, $arguments);
            $request = new RequestObjectData(ProtocolData::forrst(), 'req-001', $call);

            // Act
            $retrievedArgs = $request->getArguments();

            // Assert
            expect($retrievedArgs)->toBe($arguments)
                ->and($retrievedArgs)->toHaveCount(3)
                ->and($retrievedArgs['key3']['nested'])->toBe('value');
        });

        test('creates from array using inherited from method', function (): void {
            // Arrange
            $data = [
                'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
                'id' => 'array-id',
                'call' => [
                    'function' => 'array.method',
                    'arguments' => ['param1' => 'value1'],
                ],
            ];

            // Act
            $request = RequestObjectData::from($data);

            // Assert
            expect($request)->toBeInstanceOf(RequestObjectData::class)
                ->and($request->protocol)->toBeInstanceOf(ProtocolData::class)
                ->and($request->protocol->name)->toBe('forrst')
                ->and($request->id)->toBe('array-id')
                ->and($request->getFunction())->toBe('array.method')
                ->and($request->getArguments())->toBe(['param1' => 'value1']);
        });

        test('toArray returns correct Forrst structure', function (): void {
            // Arrange
            $request = RequestObjectData::asRequest(
                function: 'urn:cline:forrst:fn:test:function',
                arguments: ['arg1' => 'value1'],
                version: '1.0.0',
                id: 'req-123',
                context: ['tenant' => 'acme'],
            );

            // Act
            $array = $request->toArray();

            // Assert
            expect($array['protocol'])->toBe(['name' => 'forrst', 'version' => '0.1.0'])
                ->and($array['id'])->toBe('req-123')
                ->and($array['call']['function'])->toBe('urn:cline:forrst:fn:test:function')
                ->and($array['call']['version'])->toBe('1.0.0')
                ->and($array['call']['arguments'])->toBe(['arg1' => 'value1'])
                ->and($array['context'])->toBe(['tenant' => 'acme']);
        });
    });

    describe('Sad Paths', function (): void {
        test('returns default value when getting argument from null arguments', function (): void {
            // Arrange
            $call = new CallData('test');
            $request = new RequestObjectData(ProtocolData::forrst(), 'req-1', $call);
            $defaultValue = 'default';

            // Act
            $result = $request->getArgument('any.key', $defaultValue);

            // Assert
            expect($result)->toBe('default');
        });

        test('returns default value when argument does not exist', function (): void {
            // Arrange
            $arguments = ['existing' => 'value'];
            $call = new CallData('test', null, $arguments);
            $request = new RequestObjectData(ProtocolData::forrst(), 'req-1', $call);

            // Act
            $result = $request->getArgument('non.existent.key', 'fallback');

            // Assert
            expect($result)->toBe('fallback');
        });

        test('returns null when getting arguments that are null', function (): void {
            // Arrange
            $call = new CallData('test.null');
            $request = new RequestObjectData(ProtocolData::forrst(), 'id-null-args', $call);

            // Act
            $args = $request->getArguments();

            // Assert
            expect($args)->toBeNull();
        });

        test('returns null as default when argument not found and no default provided', function (): void {
            // Arrange
            $call = new CallData('test', null, ['key' => 'value']);
            $request = new RequestObjectData(ProtocolData::forrst(), 'req-1', $call);

            // Act
            $result = $request->getArgument('missing.key');

            // Assert
            expect($result)->toBeNull();
        });

        test('throws exception when ID is missing in from method', function (): void {
            // Arrange
            $data = [
                'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
                'call' => [
                    'function' => 'test.method',
                ],
            ];

            // Act & Assert
            expect(fn (): RequestObjectData => RequestObjectData::from($data))
                ->toThrow(InvalidRequestIdException::class);
        });

        test('throws exception when function is missing in from method', function (): void {
            // Arrange
            $data = [
                'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
                'id' => 'req-1',
                'call' => [],
            ];

            // Act & Assert
            expect(fn (): RequestObjectData => RequestObjectData::from($data))
                ->toThrow(MissingFunctionNameException::class);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty arguments array', function (): void {
            // Arrange
            $call = new CallData('test', null, []);
            $request = new RequestObjectData(ProtocolData::forrst(), 'empty-args', $call);

            // Act
            $args = $request->getArguments();
            $argValue = $request->getArgument('any.key', 'default');

            // Assert
            expect($args)->toBe([])
                ->and($args)->toBeEmpty()
                ->and($argValue)->toBe('default');
        });

        test('handles deeply nested argument access', function (): void {
            // Arrange
            $arguments = [
                'level1' => [
                    'level2' => [
                        'level3' => [
                            'level4' => [
                                'deep' => 'value',
                            ],
                        ],
                    ],
                ],
            ];
            $call = new CallData('test', null, $arguments);
            $request = new RequestObjectData(ProtocolData::forrst(), 'req-1', $call);

            // Act
            $deepValue = $request->getArgument('level1.level2.level3.level4.deep');

            // Assert
            expect($deepValue)->toBe('value');
        });

        test('handles function names with special characters', function (): void {
            // Arrange
            $functionWithDots = 'namespace.sub.function_name-123';

            // Act
            $request = RequestObjectData::asRequest($functionWithDots);

            // Assert
            expect($request->getFunction())->toBe('namespace.sub.function_name-123');
        });

        test('handles complex data structures in arguments', function (): void {
            // Arrange
            $complexArgs = [
                'array' => [1, 2, 3],
                'object' => ['key' => 'value'],
                'mixed' => [
                    'string' => 'text',
                    'number' => 42,
                    'float' => 3.14,
                    'bool' => true,
                    'null' => null,
                ],
            ];
            $call = new CallData('test', null, $complexArgs);
            $request = new RequestObjectData(ProtocolData::forrst(), 'req-1', $call);

            // Act
            $allArgs = $request->getArguments();
            $arrayArg = $request->getArgument('array');
            $mixedBool = $request->getArgument('mixed.bool');

            // Assert
            expect($allArgs)->toBe($complexArgs)
                ->and($arrayArg)->toBe([1, 2, 3])
                ->and($mixedBool)->toBeTrue();
        });

        test('handles numeric string keys in arguments', function (): void {
            // Arrange
            $arguments = [
                '0' => 'first',
                '1' => 'second',
                'normal' => 'value',
            ];
            $call = new CallData('test', null, $arguments);
            $request = new RequestObjectData(ProtocolData::forrst(), 'req-1', $call);

            // Act
            $first = $request->getArgument('0');
            $second = $request->getArgument('1');
            $normal = $request->getArgument('normal');

            // Assert
            expect($first)->toBe('first')
                ->and($second)->toBe('second')
                ->and($normal)->toBe('value');
        });

        test('handles context data', function (): void {
            // Arrange
            $context = ['auth' => ['token' => 'abc123'], 'tenant' => 'acme'];
            $call = new CallData('test');
            $request = new RequestObjectData(ProtocolData::forrst(), 'req-id', $call, $context);

            // Act
            $result = $request->getContext('auth.token');

            // Assert
            expect($result)->toBe('abc123');
        });

        test('handles unicode characters in ID', function (): void {
            // Arrange
            $unicodeId = '测试-request-тест';
            $call = new CallData('test');

            // Act
            $request = new RequestObjectData(ProtocolData::forrst(), $unicodeId, $call);

            // Assert
            expect($request->id)->toBe($unicodeId);
        });

        test('handles very long string ID', function (): void {
            // Arrange
            $longId = str_repeat('a', 10_000);
            $call = new CallData('test');

            // Act
            $request = new RequestObjectData(ProtocolData::forrst(), $longId, $call);

            // Assert
            expect($request->id)->toBe($longId)
                ->and(mb_strlen($request->id))->toBe(10_000);
        });

        test('toArray omits null context', function (): void {
            // Arrange
            $request = RequestObjectData::asRequest('urn:cline:forrst:fn:test:function', ['arg' => 'value'], null, 'req-1');

            // Act
            $array = $request->toArray();

            // Assert
            expect($array)->not->toHaveKey('context');
        });

        test('toArray omits null arguments', function (): void {
            // Arrange
            $request = RequestObjectData::asRequest('urn:cline:forrst:fn:test:function', null, null, 'req-1');

            // Act
            $array = $request->toArray();

            // Assert
            expect($array['call'])->not->toHaveKey('arguments');
        });

        test('toArray omits null version', function (): void {
            // Arrange
            $request = RequestObjectData::asRequest('urn:cline:forrst:fn:test:function', ['arg' => 'value'], null, 'req-1');

            // Act
            $array = $request->toArray();

            // Assert
            expect($array['call'])->not->toHaveKey('version');
        });
    });
});

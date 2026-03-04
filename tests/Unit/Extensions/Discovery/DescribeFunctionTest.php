<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Contracts\OperationRepositoryInterface;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Extensions\AtomicLock\AtomicLockExtension;
use Cline\Forrst\Extensions\Discovery\Functions\CapabilitiesFunction;
use Cline\Forrst\Extensions\Discovery\Functions\DescribeFunction;
use Cline\Forrst\Functions\FunctionUrn;
use Illuminate\Support\Facades\Route;
use Mockery as m;
use Tests\Support\Fakes\FullServer;

describe('DescribeFunction', function (): void {
    beforeEach(function (): void {
        // Bind mock operation repository for AsyncExtension
        $this->app->bind(
            OperationRepositoryInterface::class,
            fn () => m::mock(OperationRepositoryInterface::class),
        );

        // Bind CapabilitiesFunction with required string parameter
        $this->app->when(CapabilitiesFunction::class)
            ->needs('$serviceName')
            ->give('Test Service');

        // Bind AtomicLockExtension for lock functions
        $this->app->singleton(AtomicLockExtension::class, fn (): AtomicLockExtension => new AtomicLockExtension());

        Route::rpc(FullServer::class);
    });

    describe('Full Service Discovery', function (): void {
        test('returns complete discovery document with protocol version', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => ['function' => FunctionUrn::Describe->value],
            ]));

            // Assert
            $response->assertStatus(200);

            $data = $response->json();

            expect($data)
                ->toHaveKey('forrst', ProtocolData::VERSION)
                ->toHaveKey('discovery', DescribeFunction::DISCOVERY_VERSION)
                ->toHaveKey('info')
                ->toHaveKey('servers')
                ->toHaveKey('functions')
                ->toHaveKey('components');
        });

        test('returns all discoverable functions', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => ['function' => FunctionUrn::Describe->value],
            ]));

            // Assert
            $data = $response->json();
            $functionNames = array_column($data['functions'], 'name');

            // All system functions should be present
            expect($functionNames)
                ->toContain(FunctionUrn::Ping->value)
                ->toContain(FunctionUrn::Health->value)
                ->toContain(FunctionUrn::Capabilities->value)
                ->toContain(FunctionUrn::Describe->value)
                ->toContain(FunctionUrn::Cancel->value)
                ->toContain(FunctionUrn::OperationStatus->value)
                ->toContain(FunctionUrn::OperationList->value)
                ->toContain(FunctionUrn::OperationCancel->value)
                ->toContain(FunctionUrn::LocksStatus->value)
                ->toContain(FunctionUrn::LocksRelease->value)
                ->toContain(FunctionUrn::LocksForceRelease->value);
        });

        test('each function has required descriptor fields', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => ['function' => FunctionUrn::Describe->value],
            ]));

            // Assert
            $data = $response->json();

            foreach ($data['functions'] as $function) {
                expect($function)
                    ->toHaveKey('name')
                    ->toHaveKey('version')
                    ->toHaveKey('stability')
                    ->toHaveKey('summary')
                    ->toHaveKey('arguments')
                    ->toHaveKey('errors');

                // Name should be a non-empty string
                expect($function['name'])->toBeString()->not->toBeEmpty();

                // Version should be semantic version format
                expect($function['version'])->toMatch('/^\d+\.\d+\.\d+(-[\w.]+)?$/');

                // Stability should be one of the valid values
                expect($function['stability'])->toBeIn(['stable', 'beta', 'alpha', 'rc', 'dev']);

                // Summary should be a non-empty string
                expect($function['summary'])->toBeString()->not->toBeEmpty();

                // Arguments should be an array
                expect($function['arguments'])->toBeArray();

                // Errors should include standard errors
                expect($function['errors'])->toBeArray()->not->toBeEmpty();
            }
        });

        test('includes standard errors in each function', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => ['function' => FunctionUrn::Describe->value],
            ]));

            // Assert
            $data = $response->json();

            // These are the standard error codes that should be included in every function
            // See DescribeFunction::buildStandardErrors()
            $standardErrorCodes = [
                'PARSE_ERROR',
                'INVALID_REQUEST',
                'FUNCTION_NOT_FOUND',
                'INVALID_ARGUMENTS',
                'SCHEMA_VALIDATION_FAILED',
                'INTERNAL_ERROR',
                'UNAVAILABLE',
                'UNAUTHORIZED',
                'FORBIDDEN',
                'RATE_LIMITED',
            ];

            foreach ($data['functions'] as $function) {
                $errorCodes = array_column($function['errors'], 'code');

                foreach ($standardErrorCodes as $code) {
                    expect($errorCodes)->toContain($code);
                }
            }
        });

        test('components section contains error definitions', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => ['function' => FunctionUrn::Describe->value],
            ]));

            // Assert
            $data = $response->json();

            expect($data['components'])
                ->toHaveKey('errors')
                ->and($data['components']['errors'])->toBeArray()->not->toBeEmpty();
        });
    });

    describe('Single Function Discovery', function (): void {
        test('returns single function descriptor when function argument provided', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::Ping->value],
                ],
            ]));

            // Assert
            $data = $response->json();

            expect($data)
                ->toHaveKey('name', FunctionUrn::Ping->value)
                ->toHaveKey('version')
                ->toHaveKey('summary')
                ->toHaveKey('arguments')
                ->not->toHaveKey('forrst')
                ->not->toHaveKey('discovery')
                ->not->toHaveKey('functions');
        });

        test('returns empty array for non-existent function', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => 'non.existent.function'],
                ],
            ]));

            // Assert
            $data = $response->json();
            expect($data)->toBe([]);
        });

        test('filters by version when both function and version provided', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => [
                        'function' => FunctionUrn::Ping->value,
                        'version' => '1.0.0',
                    ],
                ],
            ]));

            // Assert
            $data = $response->json();

            expect($data)
                ->toHaveKey('name', FunctionUrn::Ping->value)
                ->toHaveKey('version', '1.0.0');
        });

        test('returns empty for wrong version', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => [
                        'function' => FunctionUrn::Ping->value,
                        'version' => '99.0.0',
                    ],
                ],
            ]));

            // Assert
            $data = $response->json();
            expect($data)->toBe([]);
        });
    });

    describe('Ping Function Descriptor', function (): void {
        test('has correct metadata', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::Ping->value],
                ],
            ]));

            // Assert
            $data = $response->json();

            expect($data['name'])->toBe(FunctionUrn::Ping->value)
                ->and($data['version'])->toBe('1.0.0')
                ->and($data['summary'])->toBeString()->not->toBeEmpty()
                ->and($data['arguments'])->toBe([])
                ->and($data['result'])->toHaveKey('schema');
        });
    });

    describe('Health Function Descriptor', function (): void {
        test('has correct metadata with optional arguments', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::Health->value],
                ],
            ]));

            // Assert
            $data = $response->json();

            expect($data['name'])->toBe(FunctionUrn::Health->value)
                ->and($data['version'])->toBe('1.0.0')
                ->and($data['summary'])->toContain('health');

            // Should have optional arguments
            $argNames = array_column($data['arguments'], 'name');
            expect($argNames)->toContain('component')->toContain('include_details');

            // Both should be optional
            foreach ($data['arguments'] as $arg) {
                expect($arg['required'])->toBeFalse();
            }

            // Result schema should have status and components
            expect($data['result']['schema']['properties'])
                ->toHaveKey('status')
                ->toHaveKey('components')
                ->toHaveKey('timestamp');
        });
    });

    describe('Cancel Function Descriptor', function (): void {
        test('has correct metadata with required token argument', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::Cancel->value],
                ],
            ]));

            // Assert
            $data = $response->json();

            expect($data['name'])->toBe(FunctionUrn::Cancel->value)
                ->and($data['summary'])->toContain('cancel');

            // Should have token argument
            $argNames = array_column($data['arguments'], 'name');
            expect($argNames)->toContain('token');

            // token should be required
            $tokenArg = collect($data['arguments'])->firstWhere('name', 'token');
            expect($tokenArg['required'])->toBeTrue()
                ->and($tokenArg['schema']['type'])->toBe('string');

            // Should have specific error definitions
            $errorCodes = array_column($data['errors'], 'code');
            expect($errorCodes)->toContain('CANCELLATION_TOKEN_UNKNOWN')
                ->toContain('CANCELLATION_TOO_LATE');
        });
    });

    describe('Capabilities Function Descriptor', function (): void {
        test('has correct metadata', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::Capabilities->value],
                ],
            ]));

            // Assert
            $data = $response->json();

            expect($data['name'])->toBe(FunctionUrn::Capabilities->value)
                ->and($data['summary'])->toContain('capabilities')
                ->and($data['result']['schema']['properties'])->toHaveKey('service')
                ->and($data['result']['schema']['properties'])->toHaveKey('protocolVersions')
                ->and($data['result']['schema']['properties'])->toHaveKey('functions');
        });
    });

    describe('Describe Function Descriptor', function (): void {
        test('has correct metadata with optional arguments', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::Describe->value],
                ],
            ]));

            // Assert
            $data = $response->json();

            expect($data['name'])->toBe(FunctionUrn::Describe->value)
                ->and($data['summary'])->toContain('Discovery');

            // Should have function and version arguments
            $argNames = array_column($data['arguments'], 'name');
            expect($argNames)->toContain('function')->toContain('version');

            // Both should be optional
            foreach ($data['arguments'] as $arg) {
                expect($arg['required'])->toBeFalse();
            }
        });
    });

    describe('Lock Status Function Descriptor', function (): void {
        test('has correct metadata with required key argument', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::LocksStatus->value],
                ],
            ]));

            // Assert
            $data = $response->json();

            expect($data['name'])->toBe(FunctionUrn::LocksStatus->value)
                ->and($data['summary'])->toContain('status');

            // Should have key argument
            $keyArg = collect($data['arguments'])->firstWhere('name', 'key');
            expect($keyArg)->not->toBeNull()
                ->and($keyArg['required'])->toBeTrue()
                ->and($keyArg['schema']['type'])->toBe('string');

            // Result should have lock status fields
            expect($data['result']['schema']['properties'])
                ->toHaveKey('key')
                ->toHaveKey('locked');
        });
    });

    describe('Lock Release Function Descriptor', function (): void {
        test('has correct metadata with required arguments', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::LocksRelease->value],
                ],
            ]));

            // Assert
            $data = $response->json();

            expect($data['name'])->toBe(FunctionUrn::LocksRelease->value)
                ->and(mb_strtolower((string) $data['summary']))->toContain('release');

            // Should have key and owner arguments
            $argNames = array_column($data['arguments'], 'name');
            expect($argNames)->toContain('key')->toContain('owner');
        });
    });

    describe('Lock Force Release Function Descriptor', function (): void {
        test('has correct metadata', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::LocksForceRelease->value],
                ],
            ]));

            // Assert
            $data = $response->json();

            expect($data['name'])->toBe(FunctionUrn::LocksForceRelease->value)
                ->and(mb_strtolower((string) $data['summary']))->toContain('force');

            // Should have key argument
            $keyArg = collect($data['arguments'])->firstWhere('name', 'key');
            expect($keyArg)->not->toBeNull()
                ->and($keyArg['required'])->toBeTrue();
        });
    });

    describe('Operation Status Function Descriptor', function (): void {
        test('has correct metadata with operation_id argument', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::OperationStatus->value],
                ],
            ]));

            // Assert
            $data = $response->json();

            expect($data['name'])->toBe(FunctionUrn::OperationStatus->value)
                ->and($data['summary'])->toContain('status');

            // Should have operation_id argument
            $opIdArg = collect($data['arguments'])->firstWhere('name', 'operation_id');
            expect($opIdArg)->not->toBeNull()
                ->and($opIdArg['required'])->toBeTrue();
        });
    });

    describe('Operation List Function Descriptor', function (): void {
        test('has correct metadata with filtering arguments', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::OperationList->value],
                ],
            ]));

            // Assert
            $data = $response->json();

            expect($data['name'])->toBe(FunctionUrn::OperationList->value)
                ->and(mb_strtolower((string) $data['summary']))->toContain('list');

            // Should have pagination/filter arguments
            $argNames = array_column($data['arguments'], 'name');
            expect($argNames)->toContain('limit')->toContain('cursor');
        });
    });

    describe('Operation Cancel Function Descriptor', function (): void {
        test('has correct metadata with operation_id argument', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::OperationCancel->value],
                ],
            ]));

            // Assert
            $data = $response->json();

            expect($data['name'])->toBe(FunctionUrn::OperationCancel->value)
                ->and(mb_strtolower((string) $data['summary']))->toContain('cancel');

            // Should have operation_id argument
            $opIdArg = collect($data['arguments'])->firstWhere('name', 'operation_id');
            expect($opIdArg)->not->toBeNull()
                ->and($opIdArg['required'])->toBeTrue();
        });
    });

    describe('Result Schema Validation', function (): void {
        test('all functions with results have valid schema structure', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => ['function' => FunctionUrn::Describe->value],
            ]));

            // Assert
            $data = $response->json();

            foreach ($data['functions'] as $function) {
                if (!isset($function['result'])) {
                    continue;
                }

                expect($function['result'])->toHaveKey('schema')
                    ->and($function['result']['schema'])->toBeArray();

                // If schema has properties, they should be an array
                if (!isset($function['result']['schema']['properties'])) {
                    continue;
                }

                expect($function['result']['schema']['properties'])->toBeArray();
            }
        });
    });

    describe('Argument Schema Validation', function (): void {
        test('all arguments have valid schema structure', function (): void {
            // Act
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => ['function' => FunctionUrn::Describe->value],
            ]));

            // Assert
            $data = $response->json();

            foreach ($data['functions'] as $function) {
                foreach ($function['arguments'] as $argument) {
                    expect($argument)
                        ->toHaveKey('name')
                        ->toHaveKey('schema')
                        ->toHaveKey('required');

                    expect($argument['name'])->toBeString()->not->toBeEmpty();
                    expect($argument['schema'])->toBeArray()->toHaveKey('type');
                    expect($argument['required'])->toBeBool();
                }
            }
        });
    });

    describe('Snapshot Tests', function (): void {
        test('ping descriptor matches snapshot', function (): void {
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::Ping->value],
                ],
            ]));

            expect($response->json())->toMatchSnapshot();
        });

        test('health descriptor matches snapshot', function (): void {
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::Health->value],
                ],
            ]));

            expect($response->json())->toMatchSnapshot();
        });

        test('cancel descriptor matches snapshot', function (): void {
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::Cancel->value],
                ],
            ]));

            expect($response->json())->toMatchSnapshot();
        });

        test('capabilities descriptor matches snapshot', function (): void {
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::Capabilities->value],
                ],
            ]));

            expect($response->json())->toMatchSnapshot();
        });

        test('describe descriptor matches snapshot', function (): void {
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::Describe->value],
                ],
            ]));

            expect($response->json())->toMatchSnapshot();
        });

        test('lock status descriptor matches snapshot', function (): void {
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::LocksStatus->value],
                ],
            ]));

            expect($response->json())->toMatchSnapshot();
        });

        test('lock release descriptor matches snapshot', function (): void {
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::LocksRelease->value],
                ],
            ]));

            expect($response->json())->toMatchSnapshot();
        });

        test('lock force release descriptor matches snapshot', function (): void {
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::LocksForceRelease->value],
                ],
            ]));

            expect($response->json())->toMatchSnapshot();
        });

        test('operation status descriptor matches snapshot', function (): void {
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::OperationStatus->value],
                ],
            ]));

            expect($response->json())->toMatchSnapshot();
        });

        test('operation list descriptor matches snapshot', function (): void {
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::OperationList->value],
                ],
            ]));

            expect($response->json())->toMatchSnapshot();
        });

        test('operation cancel descriptor matches snapshot', function (): void {
            $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
                'protocol' => ['name' => 'forrst', 'version' => ProtocolData::VERSION],
                'id' => 'test-1',
                'call' => [
                    'function' => FunctionUrn::Describe->value,
                    'arguments' => ['function' => FunctionUrn::OperationCancel->value],
                ],
            ]));

            expect($response->json())->toMatchSnapshot();
        });
    });
});

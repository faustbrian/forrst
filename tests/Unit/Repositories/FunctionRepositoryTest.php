<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Contracts\FunctionInterface;
use Cline\Forrst\Exceptions\FunctionNotFoundException;
use Cline\Forrst\Exceptions\ReservedNamespaceException;
use Cline\Forrst\Exceptions\VersionNotFoundException;
use Cline\Forrst\Extensions\Discovery\Functions\DescribeFunction;
use Cline\Forrst\Repositories\FunctionRepository;
use Tests\Support\Fakes\Functions\Subtract;
use Tests\Support\Fakes\Functions\SubtractWithBinding;
use Tests\Support\Fakes\ReservedFunctions\ReservedNamespaceFunction;

describe('FunctionRepository', function (): void {
    describe('Happy Paths', function (): void {
        test('registers and retrieves a function by name', function (): void {
            $repository = new FunctionRepository();
            $repository->register(
                new Subtract(),
            );

            expect($repository->resolve('urn:app:forrst:fn:subtract'))->toBeInstanceOf(Subtract::class);
        });

        test('registers a function using a class string', function (): void {
            $repository = new FunctionRepository();
            $repository->register(Subtract::class);

            expect($repository->resolve('urn:app:forrst:fn:subtract'))->toBeInstanceOf(FunctionInterface::class);
        });

        test('retrieves all registered functions indexed by name@version', function (): void {
            $repository = new FunctionRepository();
            $repository->register(Subtract::class);
            $repository->register(SubtractWithBinding::class);

            $functions = $repository->all();

            expect($functions)->toHaveCount(2);
            expect($functions)->toHaveKey('urn:app:forrst:fn:subtract@1.0.0');
            expect($functions)->toHaveKey('urn:app:forrst:fn:subtract-with-binding@1.0.0');
        });

        test('retrieves all versions for a function', function (): void {
            $repository = new FunctionRepository();
            $repository->register(Subtract::class);

            $versions = $repository->getVersions('urn:app:forrst:fn:subtract');

            expect($versions)->toBe(['1.0.0']);
        });
    });

    describe('Version Resolution', function (): void {
        test('resolves exact version', function (): void {
            $repository = new FunctionRepository();
            $repository->register(Subtract::class);

            $function = $repository->resolve('urn:app:forrst:fn:subtract', '1.0.0');

            expect($function)->toBeInstanceOf(Subtract::class);
        });

        test('resolves latest stable when version is null', function (): void {
            $repository = new FunctionRepository();
            $repository->register(Subtract::class);

            $function = $repository->resolve('urn:app:forrst:fn:subtract');

            expect($function)->toBeInstanceOf(Subtract::class);
            expect($function->getVersion())->toBe('1.0.0');
        });

        test('resolves by stability alias', function (): void {
            $repository = new FunctionRepository();
            $repository->register(Subtract::class);

            $function = $repository->resolve('urn:app:forrst:fn:subtract', 'stable');

            expect($function)->toBeInstanceOf(Subtract::class);
        });
    });

    describe('Sad Paths', function (): void {
        test('throws exception when function is not found', function (): void {
            $repository = new FunctionRepository();

            $repository->resolve('nonExistentMethod');
        })->throws(FunctionNotFoundException::class);

        test('throws exception when exact version is not found', function (): void {
            $repository = new FunctionRepository();
            $repository->register(Subtract::class);

            $repository->resolve('urn:app:forrst:fn:subtract', '99.0.0');
        })->throws(VersionNotFoundException::class);

        test('throws exception when stability has no matching versions', function (): void {
            $repository = new FunctionRepository();
            $repository->register(Subtract::class); // Only stable version

            $repository->resolve('urn:app:forrst:fn:subtract', 'beta');
        })->throws(VersionNotFoundException::class);

        test('throws exception when registering duplicate name@version', function (): void {
            $repository = new FunctionRepository();
            $repository->register(Subtract::class);
            $repository->register(Subtract::class);
        })->throws(RuntimeException::class);

        test('throws exception when user function uses reserved namespace', function (): void {
            $repository = new FunctionRepository();
            $repository->register(ReservedNamespaceFunction::class);
        })->throws(ReservedNamespaceException::class, 'urn:forrst:');
    });

    describe('Reserved Namespace Enforcement', function (): void {
        test('allows system functions to use forrst namespace', function (): void {
            $repository = new FunctionRepository();

            $repository->register(DescribeFunction::class);

            expect($repository->resolve('urn:cline:forrst:ext:discovery:fn:describe'))->toBeInstanceOf(FunctionInterface::class);
        });

        test('blocks user functions from using forrst namespace', function (): void {
            $repository = new FunctionRepository();

            expect(fn () => $repository->register(ReservedNamespaceFunction::class))
                ->toThrow(ReservedNamespaceException::class);
        });

        test('exception message contains function name and reserved namespace', function (): void {
            $repository = new FunctionRepository();

            try {
                $repository->register(ReservedNamespaceFunction::class);
                $this->fail('Expected ReservedNamespaceException to be thrown');
            } catch (ReservedNamespaceException $reservedNamespaceException) {
                expect($reservedNamespaceException->getMessage())->toContain('urn:forrst:forrst:fn:test')
                    ->and($reservedNamespaceException->getMessage())->toContain('urn:forrst:');
            }
        });

        test('allows functions with non-reserved namespaces', function (): void {
            $repository = new FunctionRepository();

            $repository->register(Subtract::class);

            expect($repository->resolve('urn:app:forrst:fn:subtract'))->toBeInstanceOf(FunctionInterface::class);
        });
    });

    describe('Backwards Compatibility', function (): void {
        test('get() without version resolves to latest stable', function (): void {
            $repository = new FunctionRepository();
            $repository->register(Subtract::class);

            $function = $repository->get('urn:app:forrst:fn:subtract');

            expect($function)->toBeInstanceOf(Subtract::class);
        });

        test('get() with exact version returns exact match', function (): void {
            $repository = new FunctionRepository();
            $repository->register(Subtract::class);

            $function = $repository->get('urn:app:forrst:fn:subtract', '1.0.0');

            expect($function)->toBeInstanceOf(Subtract::class);
        });
    });
});

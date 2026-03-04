<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Contracts\FunctionInterface;
use Cline\Forrst\Exceptions\FunctionNotFoundException;
use Cline\Forrst\Exceptions\VersionNotFoundException;
use Cline\Forrst\Repositories\FunctionRepository;
use Tests\Support\Fakes\Functions\Versioned\CalculatorV1;
use Tests\Support\Fakes\Functions\Versioned\CalculatorV2;
use Tests\Support\Fakes\Functions\Versioned\CalculatorV3Alpha1;
use Tests\Support\Fakes\Functions\Versioned\CalculatorV3Beta1;
use Tests\Support\Fakes\Functions\Versioned\CalculatorV3Beta2;

describe('Version Resolution', function (): void {
    beforeEach(function (): void {
        $this->repository = new FunctionRepository();
        $this->repository->register(CalculatorV1::class);
        $this->repository->register(CalculatorV2::class);
        $this->repository->register(CalculatorV3Alpha1::class);
        $this->repository->register(CalculatorV3Beta1::class);
        $this->repository->register(CalculatorV3Beta2::class);
    });

    describe('Exact Version Matching', function (): void {
        test('resolves exact stable version', function (): void {
            $function = $this->repository->resolve('urn:app:forrst:fn:math:calculator', '1.0.0');

            expect($function)->toBeInstanceOf(CalculatorV1::class);
            expect($function->getVersion())->toBe('1.0.0');
        });

        test('resolves exact stable version 2.0.0', function (): void {
            $function = $this->repository->resolve('urn:app:forrst:fn:math:calculator', '2.0.0');

            expect($function)->toBeInstanceOf(CalculatorV2::class);
            expect($function->getVersion())->toBe('2.0.0');
        });

        test('resolves exact beta version', function (): void {
            $function = $this->repository->resolve('urn:app:forrst:fn:math:calculator', '3.0.0-beta.1');

            expect($function)->toBeInstanceOf(CalculatorV3Beta1::class);
            expect($function->getVersion())->toBe('3.0.0-beta.1');
        });

        test('resolves exact alpha version', function (): void {
            $function = $this->repository->resolve('urn:app:forrst:fn:math:calculator', '3.0.0-alpha.1');

            expect($function)->toBeInstanceOf(CalculatorV3Alpha1::class);
            expect($function->getVersion())->toBe('3.0.0-alpha.1');
        });

        test('throws VersionNotFoundException for non-existent version', function (): void {
            $this->repository->resolve('urn:app:forrst:fn:math:calculator', '99.0.0');
        })->throws(VersionNotFoundException::class);
    });

    describe('Stability Alias Resolution', function (): void {
        test('resolves "stable" to latest stable version (2.0.0)', function (): void {
            $function = $this->repository->resolve('urn:app:forrst:fn:math:calculator', 'stable');

            expect($function)->toBeInstanceOf(CalculatorV2::class);
            expect($function->getVersion())->toBe('2.0.0');
        });

        test('resolves "beta" to latest beta version (3.0.0-beta.2)', function (): void {
            $function = $this->repository->resolve('urn:app:forrst:fn:math:calculator', 'beta');

            expect($function)->toBeInstanceOf(CalculatorV3Beta2::class);
            expect($function->getVersion())->toBe('3.0.0-beta.2');
        });

        test('resolves "alpha" to latest alpha version (3.0.0-alpha.1)', function (): void {
            $function = $this->repository->resolve('urn:app:forrst:fn:math:calculator', 'alpha');

            expect($function)->toBeInstanceOf(CalculatorV3Alpha1::class);
            expect($function->getVersion())->toBe('3.0.0-alpha.1');
        });

        test('throws VersionNotFoundException for stability with no matching versions', function (): void {
            $this->repository->resolve('urn:app:forrst:fn:math:calculator', 'rc');
        })->throws(VersionNotFoundException::class);
    });

    describe('Default Resolution (null version)', function (): void {
        test('resolves to latest stable when version is null', function (): void {
            $function = $this->repository->resolve('urn:app:forrst:fn:math:calculator');

            expect($function)->toBeInstanceOf(CalculatorV2::class);
            expect($function->getVersion())->toBe('2.0.0');
        });

        test('resolves to latest stable ignoring prerelease versions', function (): void {
            $function = $this->repository->resolve('urn:app:forrst:fn:math:calculator', null);

            // Should get 2.0.0, not 3.0.0-beta.2 (even though 3.0.0-beta.2 > 2.0.0)
            expect($function->getVersion())->toBe('2.0.0');
        });
    });

    describe('Version Index', function (): void {
        test('returns all versions for a function', function (): void {
            $versions = $this->repository->getVersions('urn:app:forrst:fn:math:calculator');

            expect($versions)->toContain('1.0.0');
            expect($versions)->toContain('2.0.0');
            expect($versions)->toContain('3.0.0-alpha.1');
            expect($versions)->toContain('3.0.0-beta.1');
            expect($versions)->toContain('3.0.0-beta.2');
            expect($versions)->toHaveCount(5);
        });

        test('returns empty array for non-existent function', function (): void {
            $versions = $this->repository->getVersions('nonexistent.function');

            expect($versions)->toBe([]);
        });
    });

    describe('Error Details', function (): void {
        test('VersionNotFoundException includes available versions', function (): void {
            try {
                $this->repository->resolve('urn:app:forrst:fn:math:calculator', '99.0.0');
                $this->fail('Expected VersionNotFoundException');
            } catch (VersionNotFoundException $versionNotFoundException) {
                $details = $versionNotFoundException->getErrorDetails();

                expect($details)->not->toBeNull();
                expect($details['function'])->toBe('urn:app:forrst:fn:math:calculator');
                expect($details['requested_version'])->toBe('99.0.0');
                expect($details['available_versions'])->toContain('1.0.0');
                expect($details['available_versions'])->toContain('2.0.0');
            }
        });

        test('FunctionNotFoundException includes function name', function (): void {
            try {
                $this->repository->resolve('nonexistent.function');
                $this->fail('Expected FunctionNotFoundException');
            } catch (FunctionNotFoundException $functionNotFoundException) {
                expect($functionNotFoundException->getMessage())->toContain('nonexistent.function');
            }
        });
    });

    describe('Edge Cases', function (): void {
        test('handles function with only prerelease versions', function (): void {
            $repository = new FunctionRepository();
            $repository->register(CalculatorV3Beta1::class);
            $repository->register(CalculatorV3Beta2::class);

            // No stable version exists, so "stable" should throw
            expect(fn (): FunctionInterface => $repository->resolve('urn:app:forrst:fn:math:calculator', 'stable'))
                ->toThrow(VersionNotFoundException::class);

            // But "beta" should work
            $function = $repository->resolve('urn:app:forrst:fn:math:calculator', 'beta');
            expect($function->getVersion())->toBe('3.0.0-beta.2');
        });

        test('handles function with only one version', function (): void {
            $repository = new FunctionRepository();
            $repository->register(CalculatorV1::class);

            $function = $repository->resolve('urn:app:forrst:fn:math:calculator');

            expect($function->getVersion())->toBe('1.0.0');
        });

        test('all() returns functions indexed by name@version', function (): void {
            $functions = $this->repository->all();

            expect($functions)->toHaveKey('urn:app:forrst:fn:math:calculator@1.0.0');
            expect($functions)->toHaveKey('urn:app:forrst:fn:math:calculator@2.0.0');
            expect($functions)->toHaveKey('urn:app:forrst:fn:math:calculator@3.0.0-alpha.1');
            expect($functions)->toHaveKey('urn:app:forrst:fn:math:calculator@3.0.0-beta.1');
            expect($functions)->toHaveKey('urn:app:forrst:fn:math:calculator@3.0.0-beta.2');
        });
    });
});

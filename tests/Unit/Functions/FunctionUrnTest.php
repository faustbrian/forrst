<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Functions;

use Cline\Forrst\Functions\FunctionUrn;

use function describe;
use function expect;
use function test;

describe('FunctionUrn', function (): void {
    describe('all()', function (): void {
        test('returns all function URN values', function (): void {
            $urns = FunctionUrn::all();

            expect($urns)->toBeArray()
                ->toContain('urn:cline:forrst:ext:diagnostics:fn:ping')
                ->toContain('urn:cline:forrst:ext:discovery:fn:capabilities')
                ->toContain('urn:cline:forrst:ext:discovery:fn:describe')
                ->toContain('urn:cline:forrst:ext:diagnostics:fn:health');
        });
    });

    describe('isSystem()', function (): void {
        test('returns true for system function URNs', function (): void {
            expect(FunctionUrn::isSystem('urn:cline:forrst:ext:diagnostics:fn:ping'))->toBeTrue();
            expect(FunctionUrn::isSystem('urn:cline:forrst:ext:discovery:fn:describe'))->toBeTrue();
            expect(FunctionUrn::isSystem('urn:cline:forrst:ext:async:fn:status'))->toBeTrue();
        });

        test('returns false for non-system function URNs', function (): void {
            expect(FunctionUrn::isSystem('urn:cline:forrst:fn:custom'))->toBeFalse();
            expect(FunctionUrn::isSystem('invalid'))->toBeFalse();
        });
    });

    describe('enum cases', function (): void {
        test('Ping has correct URN', function (): void {
            expect(FunctionUrn::Ping->value)->toBe('urn:cline:forrst:ext:diagnostics:fn:ping');
        });

        test('Capabilities has correct URN', function (): void {
            expect(FunctionUrn::Capabilities->value)->toBe('urn:cline:forrst:ext:discovery:fn:capabilities');
        });

        test('Describe has correct URN', function (): void {
            expect(FunctionUrn::Describe->value)->toBe('urn:cline:forrst:ext:discovery:fn:describe');
        });

        test('Health has correct URN', function (): void {
            expect(FunctionUrn::Health->value)->toBe('urn:cline:forrst:ext:diagnostics:fn:health');
        });

        test('Cancel has correct URN', function (): void {
            expect(FunctionUrn::Cancel->value)->toBe('urn:cline:forrst:ext:cancellation:fn:cancel');
        });

        test('OperationStatus has correct URN', function (): void {
            expect(FunctionUrn::OperationStatus->value)->toBe('urn:cline:forrst:ext:async:fn:status');
        });

        test('OperationCancel has correct URN', function (): void {
            expect(FunctionUrn::OperationCancel->value)->toBe('urn:cline:forrst:ext:async:fn:cancel');
        });

        test('OperationList has correct URN', function (): void {
            expect(FunctionUrn::OperationList->value)->toBe('urn:cline:forrst:ext:async:fn:list');
        });

        test('LocksStatus has correct URN', function (): void {
            expect(FunctionUrn::LocksStatus->value)->toBe('urn:cline:forrst:ext:atomic-lock:fn:status');
        });

        test('LocksRelease has correct URN', function (): void {
            expect(FunctionUrn::LocksRelease->value)->toBe('urn:cline:forrst:ext:atomic-lock:fn:release');
        });

        test('LocksForceRelease has correct URN', function (): void {
            expect(FunctionUrn::LocksForceRelease->value)->toBe('urn:cline:forrst:ext:atomic-lock:fn:force-release');
        });
    });
});

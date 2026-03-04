<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Forrst\Exceptions\InvalidUrnFormatException;
use Cline\Forrst\Urn;

use function describe;
use function expect;
use function test;

describe('Urn', function (): void {
    describe('extension()', function (): void {
        test('builds extension URN with default vendor', function (): void {
            expect(Urn::extension('async'))->toBe('urn:cline:forrst:ext:async');
            expect(Urn::extension('rate-limit'))->toBe('urn:cline:forrst:ext:rate-limit');
        });

        test('builds extension URN with custom vendor', function (): void {
            expect(Urn::extension('custom', 'acme'))->toBe('urn:acme:forrst:ext:custom');
        });
    });

    describe('function()', function (): void {
        test('builds function URN with default vendor', function (): void {
            expect(Urn::function('ping'))->toBe('urn:cline:forrst:fn:ping');
            expect(Urn::function('orders:create'))->toBe('urn:cline:forrst:fn:orders:create');
        });

        test('builds function URN with custom vendor', function (): void {
            expect(Urn::function('custom', 'acme'))->toBe('urn:acme:forrst:fn:custom');
        });
    });

    describe('extensionFunction()', function (): void {
        test('builds extension function URN with default vendor', function (): void {
            expect(Urn::extensionFunction('atomic-lock', 'acquire'))
                ->toBe('urn:cline:forrst:ext:atomic-lock:fn:acquire');
        });

        test('builds extension function URN with custom vendor', function (): void {
            expect(Urn::extensionFunction('custom', 'action', 'acme'))
                ->toBe('urn:acme:forrst:ext:custom:fn:action');
        });
    });

    describe('parse()', function (): void {
        test('parses extension URN', function (): void {
            $result = Urn::parse('urn:cline:forrst:ext:async');

            expect($result)->toBe([
                'vendor' => 'cline',
                'type' => 'ext',
                'name' => 'async',
            ]);
        });

        test('parses function URN', function (): void {
            $result = Urn::parse('urn:cline:forrst:fn:ping');

            expect($result)->toBe([
                'vendor' => 'cline',
                'type' => 'fn',
                'name' => 'ping',
            ]);
        });

        test('parses extension function URN', function (): void {
            $result = Urn::parse('urn:cline:forrst:ext:atomic-lock:fn:acquire');

            expect($result)->toBe([
                'vendor' => 'cline',
                'type' => 'fn',
                'extension' => 'atomic-lock',
                'name' => 'acquire',
            ]);
        });

        test('throws for invalid URN format', function (): void {
            expect(fn (): array => Urn::parse('invalid'))
                ->toThrow(InvalidUrnFormatException::class);
        });
    });

    describe('isValid()', function (): void {
        test('returns true for valid extension URNs', function (): void {
            expect(Urn::isValid('urn:cline:forrst:ext:async'))->toBeTrue();
            expect(Urn::isValid('urn:acme:forrst:ext:custom'))->toBeTrue();
        });

        test('returns true for valid function URNs', function (): void {
            expect(Urn::isValid('urn:cline:forrst:fn:ping'))->toBeTrue();
            expect(Urn::isValid('urn:cline:forrst:ext:atomic-lock:fn:acquire'))->toBeTrue();
        });

        test('returns false for invalid URNs', function (): void {
            expect(Urn::isValid('invalid'))->toBeFalse();
            expect(Urn::isValid('urn:'))->toBeFalse();
            expect(Urn::isValid(''))->toBeFalse();
        });
    });

    describe('isCore()', function (): void {
        test('returns true for cline vendor URNs', function (): void {
            expect(Urn::isCore('urn:cline:forrst:ext:async'))->toBeTrue();
            expect(Urn::isCore('urn:cline:forrst:fn:ping'))->toBeTrue();
        });

        test('returns false for non-cline vendor URNs', function (): void {
            expect(Urn::isCore('urn:acme:forrst:ext:custom'))->toBeFalse();
        });
    });

    describe('constants', function (): void {
        test('VENDOR is cline', function (): void {
            expect(Urn::VENDOR)->toBe('cline');
        });

        test('PROTOCOL is forrst', function (): void {
            expect(Urn::PROTOCOL)->toBe('forrst');
        });

        test('TYPE_EXTENSION is ext', function (): void {
            expect(Urn::TYPE_EXTENSION)->toBe('ext');
        });

        test('TYPE_FUNCTION is fn', function (): void {
            expect(Urn::TYPE_FUNCTION)->toBe('fn');
        });
    });
});

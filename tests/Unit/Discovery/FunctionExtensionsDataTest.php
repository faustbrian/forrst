<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Discovery\FunctionExtensionsData;
use Cline\Forrst\Exceptions\EmptyFieldException;

describe('FunctionExtensionsData', function (): void {
    describe('Happy Paths', function (): void {
        test('creates instance with supported extensions (allowlist)', function (): void {
            // Arrange & Act
            $extensions = new FunctionExtensionsData(
                supported: ['urn:cline:forrst:ext:idempotency', 'urn:cline:forrst:ext:dry-run'],
            );

            // Assert
            expect($extensions->supported)->toBe(['urn:cline:forrst:ext:idempotency', 'urn:cline:forrst:ext:dry-run'])
                ->and($extensions->excluded)->toBeNull();
        });

        test('creates instance with excluded extensions (blocklist)', function (): void {
            // Arrange & Act
            $extensions = new FunctionExtensionsData(
                excluded: ['urn:cline:forrst:ext:caching'],
            );

            // Assert
            expect($extensions->excluded)->toBe(['urn:cline:forrst:ext:caching'])
                ->and($extensions->supported)->toBeNull();
        });

        test('creates empty instance for default behavior', function (): void {
            // Arrange & Act
            $extensions = new FunctionExtensionsData();

            // Assert
            expect($extensions->supported)->toBeNull()
                ->and($extensions->excluded)->toBeNull();
        });

        test('toArray includes supported extensions', function (): void {
            // Arrange
            $extensions = new FunctionExtensionsData(
                supported: ['urn:cline:forrst:ext:tracing'],
            );

            // Act
            $array = $extensions->toArray();

            // Assert
            expect($array)->toHaveKey('supported')
                ->and($array['supported'])->toBe(['urn:cline:forrst:ext:tracing']);
        });

        test('toArray includes excluded extensions', function (): void {
            // Arrange
            $extensions = new FunctionExtensionsData(
                excluded: ['urn:cline:forrst:ext:idempotency', 'urn:cline:forrst:ext:dry-run'],
            );

            // Act
            $array = $extensions->toArray();

            // Assert
            expect($array)->toHaveKey('excluded')
                ->and($array['excluded'])->toBe(['urn:cline:forrst:ext:idempotency', 'urn:cline:forrst:ext:dry-run']);
        });

        test('creates from array with supported', function (): void {
            // Arrange & Act
            $extensions = FunctionExtensionsData::create([
                'supported' => ['urn:cline:forrst:ext:deadline'],
            ]);

            // Assert
            expect($extensions->supported)->toBe(['urn:cline:forrst:ext:deadline'])
                ->and($extensions->excluded)->toBeNull();
        });

        test('creates from array with excluded', function (): void {
            // Arrange & Act
            $extensions = FunctionExtensionsData::create([
                'excluded' => ['urn:cline:forrst:ext:batch'],
            ]);

            // Assert
            expect($extensions->excluded)->toBe(['urn:cline:forrst:ext:batch'])
                ->and($extensions->supported)->toBeNull();
        });
    });

    describe('Edge Cases', function (): void {
        test('rejects empty supported array', function (): void {
            // Arrange & Act & Assert
            expect(fn (): FunctionExtensionsData => new FunctionExtensionsData(
                supported: [],
            ))->toThrow(EmptyFieldException::class, 'supported');
        });

        test('rejects empty excluded array', function (): void {
            // Arrange & Act & Assert
            expect(fn (): FunctionExtensionsData => new FunctionExtensionsData(
                excluded: [],
            ))->toThrow(EmptyFieldException::class, 'excluded');
        });

        test('handles multiple supported extensions', function (): void {
            // Arrange
            $urns = [
                'urn:cline:forrst:ext:idempotency',
                'urn:cline:forrst:ext:dry-run',
                'urn:cline:forrst:ext:tracing',
                'urn:cline:forrst:ext:deadline',
            ];

            // Act
            $extensions = new FunctionExtensionsData(supported: $urns);

            // Assert
            expect($extensions->supported)->toBe($urns)
                ->and($extensions->supported)->toHaveCount(4);
        });

        test('handles multiple excluded extensions', function (): void {
            // Arrange
            $urns = [
                'urn:cline:forrst:ext:caching',
                'urn:cline:forrst:ext:batch',
            ];

            // Act
            $extensions = new FunctionExtensionsData(excluded: $urns);

            // Assert
            expect($extensions->excluded)->toBe($urns)
                ->and($extensions->excluded)->toHaveCount(2);
        });
    });
});

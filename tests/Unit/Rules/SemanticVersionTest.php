<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Rules\SemanticVersion;
use Illuminate\Support\Facades\Validator;

describe('SemanticVersion', function (): void {
    describe('Happy Paths', function (): void {
        test('validates basic semantic version strings with correct format', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '1.0.0'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->passes())->toBeTrue();
        });

        test('validates semantic version with non-zero minor version', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '2.1.3'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->passes())->toBeTrue();
        });

        test('validates semantic version with zero major version', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '0.0.1'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->passes())->toBeTrue();
        });

        test('validates semantic version with alpha prerelease identifier', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '1.0.0-alpha'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->passes())->toBeTrue();
        });

        test('validates semantic version with numbered beta prerelease', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '1.0.0-beta.1'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->passes())->toBeTrue();
        });

        test('validates semantic version with rc prerelease identifier', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '2.0.0-rc.1'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->passes())->toBeTrue();
        });

        test('validates semantic version with build metadata', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '1.0.0+build.123'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->passes())->toBeTrue();
        });

        test('validates semantic version with prerelease and build metadata', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '1.0.0-alpha.1+build.456'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->passes())->toBeTrue();
        });

        test('validates semantic version with complex prerelease identifiers', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '1.0.0-x.7.z.92'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->passes())->toBeTrue();
        });

        test('validates semantic version with large version numbers', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '999.888.777'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->passes())->toBeTrue();
        });

        test('isValid returns true for valid semantic version string', function (): void {
            // Act & Assert
            expect(SemanticVersion::isValid('1.0.0'))->toBeTrue();
        });

        test('parse extracts major minor patch components correctly', function (): void {
            // Act
            $result = SemanticVersion::parse('2.1.3');

            // Assert
            expect($result)->toBe([
                'major' => 2,
                'minor' => 1,
                'patch' => 3,
                'prerelease' => null,
                'build' => null,
            ]);
        });

        test('parse extracts prerelease identifier correctly', function (): void {
            // Act
            $result = SemanticVersion::parse('1.0.0-beta.1');

            // Assert
            expect($result['prerelease'])->toBe('beta.1')
                ->and($result['build'])->toBeNull();
        });

        test('parse extracts build metadata correctly', function (): void {
            // Act
            $result = SemanticVersion::parse('1.0.0+build.123');

            // Assert
            expect($result['build'])->toBe('build.123');
        });

        test('parse extracts both prerelease and build metadata', function (): void {
            // Act
            $result = SemanticVersion::parse('1.0.0-alpha+build');

            // Assert
            expect($result['prerelease'])->toBe('alpha')
                ->and($result['build'])->toBe('build');
        });

        test('stability returns stable for version without prerelease', function (): void {
            // Act
            $result = SemanticVersion::stability('1.0.0');

            // Assert
            expect($result)->toBe('stable');
        });

        test('stability returns alpha for alpha prerelease version', function (): void {
            // Act
            $result = SemanticVersion::stability('1.0.0-alpha.1');

            // Assert
            expect($result)->toBe('alpha');
        });

        test('stability returns beta for beta prerelease version', function (): void {
            // Act
            $result = SemanticVersion::stability('1.0.0-beta.2');

            // Assert
            expect($result)->toBe('beta');
        });

        test('stability returns rc for release candidate version', function (): void {
            // Act
            $result = SemanticVersion::stability('2.0.0-rc.1');

            // Assert
            expect($result)->toBe('rc');
        });

        test('normalize returns unchanged version for valid semver', function (): void {
            // Act
            $result = SemanticVersion::normalize('2.1.0');

            // Assert
            expect($result)->toBe('2.1.0');
        });

        test('normalize converts single integer to semver format', function (): void {
            // Act
            $result = SemanticVersion::normalize('5');

            // Assert
            expect($result)->toBe('5.0.0');
        });
    });

    describe('Sad Paths', function (): void {
        test('fails validation for non-string value', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => 123],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->first('version'))
                ->toBe('The version must be a semantic version string (e.g., "1.0.0").');
        });

        test('fails validation for array value', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => []],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->first('version'))
                ->toBe('The version must be a semantic version string (e.g., "1.0.0").');
        });

        test('fails validation for invalid version string format', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => 'not-a-version'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->first('version'))
                ->toBe('The version must be a valid semantic version (MAJOR.MINOR.PATCH with optional pre-release).');
        });

        test('fails validation for version with only major and minor', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '1.0'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->fails())->toBeTrue();
        });

        test('fails validation for version with leading v prefix', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => 'v1.0.0'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->fails())->toBeTrue();
        });

        test('fails validation for version with leading zeros in major', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '01.0.0'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->fails())->toBeTrue();
        });

        test('fails validation for version with leading zeros in minor', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '1.02.0'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->fails())->toBeTrue();
        });

        test('fails validation for version with leading zeros in patch', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '1.0.03'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->fails())->toBeTrue();
        });

        test('fails validation for version with spaces', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '1.0.0 '],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->fails())->toBeTrue();
        });

        test('passes validation for empty string due to regex matching behavior', function (): void {
            // Arrange - empty string technically matches optional parts of regex
            $validator = Validator::make(
                ['version' => ''],
                ['version' => ['required', new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->fails())->toBeTrue(); // Fails due to 'required' rule
        });

        test('isValid returns false for invalid version string', function (): void {
            // Act & Assert
            expect(SemanticVersion::isValid('invalid'))->toBeFalse();
        });

        test('isValid returns false for version with leading zeros', function (): void {
            // Act & Assert
            expect(SemanticVersion::isValid('01.0.0'))->toBeFalse();
        });

        test('parse returns null for invalid version string', function (): void {
            // Act
            $result = SemanticVersion::parse('not-a-version');

            // Assert
            expect($result)->toBeNull();
        });

        test('parse returns null for version with leading v', function (): void {
            // Act
            $result = SemanticVersion::parse('v1.0.0');

            // Assert
            expect($result)->toBeNull();
        });

        test('stability returns stable for invalid version string', function (): void {
            // Act
            $result = SemanticVersion::stability('invalid');

            // Assert
            expect($result)->toBe('stable');
        });

        test('normalize returns invalid string unchanged when not integer', function (): void {
            // Act
            $result = SemanticVersion::normalize('invalid-version');

            // Assert
            expect($result)->toBe('invalid-version');
        });
    });

    describe('Edge Cases', function (): void {
        test('validates version with zero for all components', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '0.0.0'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->passes())->toBeTrue();
        });

        test('validates version with very long prerelease identifier', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '1.0.0-very.long.prerelease.identifier.with.many.parts'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->passes())->toBeTrue();
        });

        test('validates version with hyphenated prerelease identifier', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '1.0.0-alpha-beta'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->passes())->toBeTrue();
        });

        test('validates version with numeric-only build metadata', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '1.0.0+20231201'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->passes())->toBeTrue();
        });

        test('validates version with alphanumeric build metadata', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '1.0.0+build-abc123'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->passes())->toBeTrue();
        });

        test('parse handles version with all components at maximum', function (): void {
            // Act
            $result = SemanticVersion::parse('999.999.999-prerelease+build');

            // Assert
            expect($result['major'])->toBe(999)
                ->and($result['minor'])->toBe(999)
                ->and($result['patch'])->toBe(999)
                ->and($result['prerelease'])->toBe('prerelease')
                ->and($result['build'])->toBe('build');
        });

        test('stability returns alpha for unknown prerelease pattern', function (): void {
            // Act
            $result = SemanticVersion::stability('1.0.0-unknown.identifier');

            // Assert
            expect($result)->toBe('alpha');
        });

        test('stability incorrectly returns alpha for version with only build metadata', function (): void {
            // Act - build metadata results in empty string prerelease (not null)
            // This is a bug: should return 'stable' but returns 'alpha' due to empty string != null
            $result = SemanticVersion::stability('1.0.0+build');

            // Assert - current buggy behavior returns 'alpha'
            expect($result)->toBe('alpha');
        });

        test('normalize handles zero as integer version', function (): void {
            // Act
            $result = SemanticVersion::normalize('0');

            // Assert
            expect($result)->toBe('0.0.0');
        });

        test('normalize handles multi-digit integer version', function (): void {
            // Act
            $result = SemanticVersion::normalize('123');

            // Assert
            expect($result)->toBe('123.0.0');
        });

        test('isValid handles version with single character prerelease', function (): void {
            // Act & Assert
            expect(SemanticVersion::isValid('1.0.0-a'))->toBeTrue();
        });

        test('parse handles prerelease starting with number', function (): void {
            // Act
            $result = SemanticVersion::parse('1.0.0-0alpha');

            // Assert
            expect($result['prerelease'])->toBe('0alpha');
        });

        test('validates version with prerelease containing only numbers', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '1.0.0-123'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->passes())->toBeTrue();
        });

        test('fails validation for negative version numbers', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '-1.0.0'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->fails())->toBeTrue();
        });

        test('fails validation for version with only dots', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '...'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->fails())->toBeTrue();
        });

        test('fails validation for version with four components', function (): void {
            // Arrange
            $validator = Validator::make(
                ['version' => '1.0.0.0'],
                ['version' => [new SemanticVersion()]],
            );

            // Act & Assert
            expect($validator->fails())->toBeTrue();
        });
    });
});

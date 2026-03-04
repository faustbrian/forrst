<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Override;

use function is_string;
use function preg_match;
use function str_starts_with;

/**
 * Validation rule for semantic versioning.
 *
 * Validates that function version values conform to the semantic versioning
 * specification (semver 2.0.0). Accepts versions in the format MAJOR.MINOR.PATCH
 * with optional pre-release identifiers and build metadata. Also provides utility
 * methods for parsing version components, determining stability, and normalizing
 * version strings.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/versioning
 * @see https://semver.org/
 */
final class SemanticVersion implements ValidationRule
{
    /**
     * Semantic version regex pattern compliant with semver 2.0.0 specification.
     *
     * Captures five groups: major, minor, patch, prerelease, and build metadata.
     * Validates version format while allowing optional prerelease and build identifiers.
     *
     * Examples:
     * - 1.0.0 (stable release)
     * - 2.1.0 (minor update)
     * - 3.0.0-beta.1 (beta prerelease)
     * - 1.0.0-alpha+build.123 (alpha with build metadata)
     */
    private const string SEMVER_PATTERN = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/';

    /**
     * Check if a value is a valid semantic version.
     *
     * Utility method for quick validation without throwing exceptions. Returns
     * true if the value matches the semver pattern, false otherwise.
     *
     * @param  string $value Version string to validate
     * @return bool   True if the string is a valid semantic version, false otherwise
     */
    public static function isValid(string $value): bool
    {
        return preg_match(self::SEMVER_PATTERN, $value) === 1;
    }

    /**
     * Parse a semantic version into components.
     *
     * Extracts major, minor, patch, prerelease, and build metadata from a version
     * string. Returns null if the version string is invalid. The returned array
     * provides structured access to version components for comparison and analysis.
     *
     * @param  string                                                                              $value Version string to parse
     * @return null|array{major: int, minor: int, patch: int, prerelease: ?string, build: ?string} Associative array of version components, or null if invalid
     */
    public static function parse(string $value): ?array
    {
        if (!preg_match(self::SEMVER_PATTERN, $value, $matches)) {
            return null;
        }

        return [
            'major' => (int) $matches[1],
            'minor' => (int) $matches[2],
            'patch' => (int) $matches[3],
            'prerelease' => $matches[4] ?? null,
            'build' => $matches[5] ?? null,
        ];
    }

    /**
     * Derive stability from a semantic version's prerelease identifier.
     *
     * Analyzes the prerelease portion of a version string to determine its stability
     * level. Used for version comparison and compatibility checks. Unknown prerelease
     * patterns default to 'alpha' as the least stable designation.
     *
     * Stability levels:
     * - No prerelease → 'stable'
     * - alpha.* → 'alpha'
     * - beta.* → 'beta'
     * - rc.* → 'rc' (release candidate)
     * - Unknown pattern → 'alpha' (conservative default)
     *
     * @param  string $value Semantic version string to analyze
     * @return string Stability level: 'stable', 'alpha', 'beta', or 'rc'
     */
    public static function stability(string $value): string
    {
        $parsed = self::parse($value);

        if ($parsed === null || $parsed['prerelease'] === null) {
            return 'stable';
        }

        $prerelease = $parsed['prerelease'];

        if (str_starts_with($prerelease, 'alpha')) {
            return 'alpha';
        }

        if (str_starts_with($prerelease, 'beta')) {
            return 'beta';
        }

        if (str_starts_with($prerelease, 'rc')) {
            return 'rc';
        }

        // Unknown prerelease pattern defaults to alpha (less stable)
        return 'alpha';
    }

    /**
     * Normalize an integer version to semver format.
     *
     * Converts simple integer versions to full semantic version format for
     * backwards compatibility with legacy version numbering schemes. Already
     * valid semantic versions are returned unchanged.
     *
     * Examples:
     * - "1" → "1.0.0"
     * - "5" → "5.0.0"
     * - "2.1.0" → "2.1.0" (unchanged)
     *
     * @param  string $value Version string (integer or semver format)
     * @return string Normalized semantic version string
     */
    public static function normalize(string $value): string
    {
        // If it's already a valid semver, return as-is
        if (self::isValid($value)) {
            return $value;
        }

        // If it's a simple integer, normalize to semver
        if (preg_match('/^\d+$/', $value)) {
            return $value.'.0.0';
        }

        // Return as-is (will fail validation)
        return $value;
    }

    /**
     * Validate that the given value is a valid semantic version.
     *
     * @param string  $attribute The name of the attribute being validated
     * @param mixed   $value     The value to validate
     * @param Closure $fail      Closure to invoke with error message if validation fails
     */
    #[Override()]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('The :attribute must be a semantic version string (e.g., "1.0.0").');

            return;
        }

        if (preg_match(self::SEMVER_PATTERN, $value)) {
            return;
        }

        $fail('The :attribute must be a valid semantic version (MAJOR.MINOR.PATCH with optional pre-release).');
    }
}

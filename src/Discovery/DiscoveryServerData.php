<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Discovery;

use Cline\Forrst\Exceptions\InvalidFieldTypeException;
use Cline\Forrst\Exceptions\InvalidFieldValueException;
use Cline\Forrst\Exceptions\InvalidUrlException;
use Cline\Forrst\Exceptions\MissingRequiredFieldException;
use Spatie\LaravelData\Data;

use const FILTER_VALIDATE_URL;

use function array_diff;
use function array_keys;
use function filter_var;
use function gettype;
use function is_int;
use function is_string;
use function json_encode;
use function mb_substr_count;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function sprintf;

/**
 * Server endpoint information for discovery documents.
 *
 * Defines a single server endpoint where the Forrst service can be accessed.
 * Supports URL templating with variable substitution for dynamic endpoint
 * configuration across multiple environments or regions.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/system-functions
 * @see https://docs.cline.sh/specs/forrst/discovery#server-object
 */
final class DiscoveryServerData extends Data
{
    /**
     * Create a new server endpoint definition.
     *
     * @param string                                          $name        Human-readable identifier for this server endpoint (e.g., "Production",
     *                                                                     "Staging", "US East"). Used in client tooling to display available
     *                                                                     server options and help users select the appropriate endpoint.
     * @param string                                          $url         Server URL supporting RFC 6570 URI template syntax for variable
     *                                                                     substitution (e.g., "https://{environment}.api.example.com/v1").
     *                                                                     Variables enclosed in braces are replaced with values from the
     *                                                                     variables array, enabling dynamic endpoint construction.
     * @param null|string                                     $summary     Brief one-line description of this server's purpose. Displayed
     *                                                                     in compact views and navigation lists where a full description
     *                                                                     would be too verbose.
     * @param null|string                                     $description Detailed human-readable explanation of this server's purpose,
     *                                                                     characteristics, or usage constraints. Supports Markdown. Provides
     *                                                                     context about when to use this endpoint versus alternatives.
     * @param null|array<string, ServerVariableData>          $variables   URL template variable definitions keyed
     *                                                                     by variable name. Each variable defines
     *                                                                     allowed values, default value, and
     *                                                                     description for template substitution
     *                                                                     in the URL field.
     * @param null|array<int, ServerExtensionDeclarationData> $extensions  Extensions supported by this server endpoint.
     *                                                                     Each declaration specifies the extension URN
     *                                                                     and version. Allows clients to discover which
     *                                                                     optional protocol features are available.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $url,
        public readonly ?string $summary = null,
        public readonly ?string $description = null,
        public readonly ?array $variables = null,
        public readonly ?array $extensions = null,
    ) {
        $this->validateUrlTemplate($url);
        $this->validateVariableConsistency($url, $variables);
        $this->validateArrayStructure($variables, $extensions);
    }

    /**
     * Validate RFC 6570 URI template syntax.
     *
     * @throws InvalidFieldValueException
     * @throws InvalidUrlException
     */
    private function validateUrlTemplate(string $url): void
    {
        // Check for basic template syntax errors
        if (mb_substr_count($url, '{') !== mb_substr_count($url, '}')) {
            throw InvalidUrlException::invalidFormat('url');
        }

        // Extract and validate variable names
        preg_match_all('/\{([^}]+)\}/', $url, $matches);

        foreach ($matches[1] as $varName) {
            // RFC 6570: variable names must be [A-Za-z0-9_]+ (no hyphens, dots, etc.)
            if (!preg_match('/^\w+$/', $varName)) {
                throw InvalidFieldValueException::forField(
                    'url.variable.'.$varName,
                    sprintf("Invalid variable name '%s' in URI template. ", $varName).
                    'Variable names must contain only letters, numbers, and underscores.',
                );
            }
        }

        // Validate URL structure (basic sanity check)
        $testUrl = preg_replace('/\{[^}]+\}/', 'test', $url);

        if (!filter_var($testUrl, FILTER_VALIDATE_URL)) {
            throw InvalidUrlException::invalidFormat('url');
        }
    }

    /**
     * Ensure all URL template variables are defined in $variables array.
     *
     * @param null|array<string, ServerVariableData> $variables
     *
     * @throws InvalidFieldValueException
     * @throws MissingRequiredFieldException
     */
    private function validateVariableConsistency(string $url, ?array $variables): void
    {
        preg_match_all('/\{([^}]+)\}/', $url, $matches);
        $urlVars = $matches[1];

        if ($urlVars === []) {
            return; // No variables in template
        }

        if ($variables === null) {
            throw MissingRequiredFieldException::forField('variables');
        }

        $definedVars = array_keys($variables);
        $undefinedVars = array_diff($urlVars, $definedVars);

        if ($undefinedVars !== []) {
            throw InvalidFieldValueException::forField(
                'variables',
                'URL template references undefined variables: '.json_encode($undefinedVars),
            );
        }
    }

    /**
     * Validate array structure for variables and extensions.
     *
     * @param null|array<string, ServerVariableData>          $variables
     * @param null|array<int, ServerExtensionDeclarationData> $extensions
     *
     * @throws InvalidFieldTypeException
     * @throws InvalidFieldValueException
     */
    private function validateArrayStructure(?array $variables, ?array $extensions): void
    {
        // Validate variables array structure
        if ($variables !== null) {
            foreach ($variables as $key => $value) {
                if (!is_string($key)) {
                    throw InvalidFieldValueException::forField(
                        'variables',
                        'Variables array must be keyed by variable name (string), got: '.gettype($key),
                    );
                }

                if (!$value instanceof ServerVariableData) {
                    throw InvalidFieldTypeException::forField(
                        'variables.'.$key,
                        'ServerVariableData',
                        $value,
                    );
                }
            }
        }

        // Validate extensions array structure
        if ($extensions === null) {
            return;
        }

        foreach ($extensions as $index => $value) {
            if (!is_int($index)) {
                throw InvalidFieldValueException::forField(
                    'extensions',
                    'Extensions array must be indexed by integers, got: '.gettype($index),
                );
            }

            if (!$value instanceof ServerExtensionDeclarationData) {
                throw InvalidFieldTypeException::forField(
                    sprintf('extensions[%d]', $index),
                    'ServerExtensionDeclarationData',
                    $value,
                );
            }
        }
    }
}

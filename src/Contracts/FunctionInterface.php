<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Contracts;

use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Discovery\ArgumentData;
use Cline\Forrst\Discovery\DeprecatedData;
use Cline\Forrst\Discovery\ErrorDefinitionData;
use Cline\Forrst\Discovery\ExampleData;
use Cline\Forrst\Discovery\ExternalDocsData;
use Cline\Forrst\Discovery\FunctionExtensionsData;
use Cline\Forrst\Discovery\LinkData;
use Cline\Forrst\Discovery\Query\QueryCapabilitiesData;
use Cline\Forrst\Discovery\ResultDescriptorData;
use Cline\Forrst\Discovery\SimulationScenarioData;
use Cline\Forrst\Discovery\TagData;

/**
 * Forrst function contract interface.
 *
 * Defines the contract for implementing Forrst RPC function handlers with complete
 * discovery metadata, argument validation schemas, and result specifications.
 * Functions are the primary execution units in the Forrst protocol.
 *
 * Defines the interface that all Forrst function handlers must implement
 * to provide metadata for function discovery, argument validation, and
 * result specification according to the Forrst Discovery specification.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/protocol Protocol specification
 * @see https://docs.cline.sh/forrst/system-functions System functions reference
 */
interface FunctionInterface
{
    /**
     * Get the URN (Uniform Resource Name) for this function.
     *
     * The URN uniquely identifies this function across the Forrst ecosystem.
     * URNs follow the format: urn:<vendor>:forrst:fn:<function-name>
     *
     * MUST use Urn::function() helper to ensure proper format validation.
     * This method may be called multiple times during function registration
     * and request processing. Implementations SHOULD cache the result in a
     * private constant or property.
     *
     * Example: urn:acme:forrst:fn:orders:create
     *
     * @return string Function URN identifier in valid format
     */
    public function getUrn(): string;

    /**
     * Get the function version string.
     *
     * The version is used to support multiple versions of the same function.
     * Each function name + version combination must be unique. MUST use
     * semantic versioning format with optional prerelease identifiers.
     *
     * @return string Function version (e.g., "1.0.0", "2.0.0", "3.0.0-beta.1")
     */
    public function getVersion(): string;

    /**
     * Get a brief human-readable summary of the function's purpose.
     *
     * Used in API documentation and discovery endpoints to describe what
     * the function does. Should be concise (1-2 sentences) and focus on
     * the function's primary purpose.
     *
     * @return string Function summary description
     */
    public function getSummary(): string;

    /**
     * Get argument definitions for this function.
     *
     * Returns an array of argument descriptors that define the expected
     * input structure, types, and validation rules for function invocation.
     * Used for request validation and API documentation generation.
     *
     * PERFORMANCE: This method may be called multiple times during function
     * registration and request processing. Implementations SHOULD cache the
     * result in a private constant or property.
     *
     * TYPE SAFETY: MUST return an array of ArgumentData objects. Plain arrays
     * are supported for backward compatibility but are deprecated and will be
     * removed in version 2.0.
     *
     * @return array<int, ArgumentData|array<string, mixed>> Array of argument descriptors
     */
    public function getArguments(): array;

    /**
     * Get the result descriptor for this function.
     *
     * Defines the structure and type of the successful response payload.
     * Returns null for functions that don't return a value (notifications).
     *
     * @return null|ResultDescriptorData Result descriptor or null for void functions
     */
    public function getResult(): ?ResultDescriptorData;

    /**
     * Get error definitions that this function may produce.
     *
     * Returns an array of error objects describing the possible error
     * conditions, including error codes and descriptive messages.
     *
     * PERFORMANCE: This method may be called multiple times during function
     * registration and request processing. Implementations SHOULD cache the
     * result in a private constant or property.
     *
     * TYPE SAFETY: MUST return an array of ErrorDefinitionData objects. Plain
     * arrays are supported for backward compatibility but are deprecated and
     * will be removed in version 2.0.
     *
     * @return array<int, array<string, mixed>|ErrorDefinitionData> Array of error definitions
     */
    public function getErrors(): array;

    /**
     * Get a detailed description of the function.
     *
     * Provides extended documentation beyond the summary. Markdown supported.
     *
     * @return null|string Detailed description or null if none
     */
    public function getDescription(): ?string;

    /**
     * Get tags for logical grouping of functions.
     *
     * @return null|array<int, array<string, mixed>|TagData> Array of tags or null
     */
    public function getTags(): ?array;

    /**
     * Get query capabilities for list functions.
     *
     * Defines filtering, sorting, pagination, and field selection capabilities.
     *
     * @return null|QueryCapabilitiesData Query capabilities or null
     */
    public function getQuery(): ?QueryCapabilitiesData;

    /**
     * Get deprecation information if the function is deprecated.
     *
     * @return null|DeprecatedData Deprecation info or null if not deprecated
     */
    public function getDeprecated(): ?DeprecatedData;

    /**
     * Get side effects this function may cause.
     *
     * Valid values: 'create', 'update', 'delete'.
     * Empty array or null indicates read-only.
     *
     * IMPORTANT: This method is used for security auditing and idempotency
     * checks. Accurately declaring side effects is critical for proper
     * function categorization and safe retries.
     *
     * @return null|array<int, string> Side effects or null
     */
    public function getSideEffects(): ?array;

    /**
     * Check if the function should appear in discovery responses.
     *
     * Non-discoverable functions remain callable but aren't advertised.
     *
     * @return bool True if discoverable
     */
    public function isDiscoverable(): bool;

    /**
     * Get usage examples for the function.
     *
     * @return null|array<int, array<string, mixed>|ExampleData> Examples or null
     */
    public function getExamples(): ?array;

    /**
     * Get related function links for navigation.
     *
     * @return null|array<int, array<string, mixed>|LinkData> Links or null
     */
    public function getLinks(): ?array;

    /**
     * Get external documentation reference.
     *
     * @return null|ExternalDocsData External docs or null
     */
    public function getExternalDocs(): ?ExternalDocsData;

    /**
     * Get simulation scenarios for sandbox/demo mode.
     *
     * Returns predefined input/output pairs for testing and demos.
     *
     * @return null|array<int, array<string, mixed>|SimulationScenarioData> Scenarios or null
     */
    public function getSimulations(): ?array;

    /**
     * Get per-function extension support configuration.
     *
     * Returns extension allowlist/blocklist, or null to accept all server-wide extensions.
     *
     * @return null|FunctionExtensionsData Extension support config or null
     */
    public function getExtensions(): ?FunctionExtensionsData;

    /**
     * Inject the current request object into the function handler.
     *
     * Called by the dispatcher before function execution to provide access
     * to the full request context, including arguments, ID, and metadata.
     *
     * SECURITY: The request object is assumed to be validated before injection.
     * Implementations should NOT re-validate the request structure but MAY
     * perform business logic validation on arguments.
     *
     * @param RequestObjectData $requestObject The validated incoming Forrst request
     */
    public function setRequest(RequestObjectData $requestObject): void;
}

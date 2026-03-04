<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Discovery;

use Cline\Forrst\Discovery\Resource\ResourceData;
use Cline\Forrst\Exceptions\ComponentReferenceNotFoundException;
use Spatie\LaravelData\Data;

use function array_keys;
use function array_merge;
use function count;
use function explode;
use function mb_substr;
use function str_starts_with;

/**
 * Reusable component definitions for API discovery documentation.
 *
 * Provides a centralized registry of reusable schemas, content descriptors, error definitions,
 * examples, example pairings, links, tags, and resources that can be referenced throughout
 * the discovery document using $ref notation. This promotes consistency and reduces duplication
 * in API documentation.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/discovery#components
 */
final class ComponentsData extends Data
{
    /**
     * Create a new components definition instance.
     *
     * @param null|array<string, array<string, mixed>> $schemas            Reusable JSON Schema definitions indexed by name.
     *                                                                     These schemas can be referenced using $ref notation
     *                                                                     throughout the discovery document to maintain consistency
     *                                                                     and reduce duplication of complex type definitions.
     * @param null|array<string, ArgumentData>         $contentDescriptors Reusable content descriptor definitions indexed by name.
     *                                                                     Commonly used parameters (pagination, filters, authentication)
     *                                                                     can be defined once and referenced across multiple function
     *                                                                     arguments using $ref notation.
     * @param null|array<string, ErrorDefinitionData>  $errors             Reusable error definitions indexed by error code. Defines
     *                                                                     standard error structures, status codes, and messages that
     *                                                                     functions can return, ensuring consistent error handling.
     * @param null|array<string, ExampleData>          $examples           Reusable example value definitions indexed by name. Standalone
     *                                                                     example values that can be referenced from content descriptors
     *                                                                     or result schemas to illustrate expected data formats.
     * @param null|array<string, ExamplePairingData>   $examplePairings    Reusable request-response pair definitions indexed by name.
     *                                                                     Complete function invocation examples showing arguments and
     *                                                                     expected results together, demonstrating end-to-end usage.
     * @param null|array<string, LinkData>             $links              Reusable link definitions indexed by name. Describe relationships
     *                                                                     between functions and enable clients to discover related
     *                                                                     operations and navigation paths through the API.
     * @param null|array<string, TagData>              $tags               Reusable tag definitions indexed by name. Tags provide
     *                                                                     metadata for categorizing and organizing functions within
     *                                                                     the API documentation interface.
     * @param null|array<string, ResourceData>         $resources          Reusable resource definitions indexed by type name. Defines
     *                                                                     the structure and transformation rules for domain entities
     *                                                                     returned by API functions.
     */
    public function __construct(
        public readonly ?array $schemas = null,
        public readonly ?array $contentDescriptors = null,
        public readonly ?array $errors = null,
        public readonly ?array $examples = null,
        public readonly ?array $examplePairings = null,
        public readonly ?array $links = null,
        public readonly ?array $tags = null,
        public readonly ?array $resources = null,
    ) {}

    /**
     * Validate that a component reference exists.
     *
     * @param string $ref Component reference (e.g., "#/components/schemas/User")
     *
     * @return bool True if the reference exists, false otherwise
     */
    public function hasReference(string $ref): bool
    {
        if (!str_starts_with($ref, '#/components/')) {
            return false;
        }

        $parts = explode('/', mb_substr($ref, 13)); // Remove '#/components/'

        if (count($parts) !== 2) {
            return false;
        }

        [$componentType, $componentName] = $parts;

        return match ($componentType) {
            'schemas' => isset($this->schemas[$componentName]),
            'contentDescriptors' => isset($this->contentDescriptors[$componentName]),
            'errors' => isset($this->errors[$componentName]),
            'examples' => isset($this->examples[$componentName]),
            'examplePairings' => isset($this->examplePairings[$componentName]),
            'links' => isset($this->links[$componentName]),
            'tags' => isset($this->tags[$componentName]),
            'resources' => isset($this->resources[$componentName]),
            default => false,
        };
    }

    /**
     * Resolve a component reference to its actual data.
     *
     * @param string $ref Component reference (e.g., "#/components/schemas/User")
     *
     * @throws ComponentReferenceNotFoundException If reference doesn't exist
     * @return mixed                               The resolved component data
     */
    public function resolveReference(string $ref): mixed
    {
        if (!$this->hasReference($ref)) {
            throw ComponentReferenceNotFoundException::forRef($ref);
        }

        $parts = explode('/', mb_substr($ref, 13));
        [$componentType, $componentName] = $parts;

        return match ($componentType) {
            'schemas' => $this->schemas[$componentName],
            'contentDescriptors' => $this->contentDescriptors[$componentName],
            'errors' => $this->errors[$componentName],
            'examples' => $this->examples[$componentName],
            'examplePairings' => $this->examplePairings[$componentName],
            'links' => $this->links[$componentName],
            'tags' => $this->tags[$componentName],
            'resources' => $this->resources[$componentName],
        };
    }

    /**
     * Get all component references in this components registry.
     *
     * @return array<string> Array of all valid component references
     */
    public function getAllReferences(): array
    {
        $references = [];

        if ($this->schemas !== null) {
            foreach (array_keys($this->schemas) as $name) {
                $references[] = '#/components/schemas/'.$name;
            }
        }

        if ($this->contentDescriptors !== null) {
            foreach (array_keys($this->contentDescriptors) as $name) {
                $references[] = '#/components/contentDescriptors/'.$name;
            }
        }

        if ($this->errors !== null) {
            foreach (array_keys($this->errors) as $name) {
                $references[] = '#/components/errors/'.$name;
            }
        }

        if ($this->examples !== null) {
            foreach (array_keys($this->examples) as $name) {
                $references[] = '#/components/examples/'.$name;
            }
        }

        if ($this->examplePairings !== null) {
            foreach (array_keys($this->examplePairings) as $name) {
                $references[] = '#/components/examplePairings/'.$name;
            }
        }

        if ($this->links !== null) {
            foreach (array_keys($this->links) as $name) {
                $references[] = '#/components/links/'.$name;
            }
        }

        if ($this->tags !== null) {
            foreach (array_keys($this->tags) as $name) {
                $references[] = '#/components/tags/'.$name;
            }
        }

        if ($this->resources !== null) {
            foreach (array_keys($this->resources) as $name) {
                $references[] = '#/components/resources/'.$name;
            }
        }

        return $references;
    }

    /**
     * Add a schema component.
     *
     * @param string               $name   Schema identifier
     * @param array<string, mixed> $schema JSON Schema definition
     *
     * @return self New instance with the schema added
     */
    public function withSchema(string $name, array $schema): self
    {
        $schemas = $this->schemas ?? [];
        $schemas[$name] = $schema;

        return new self(
            schemas: $schemas,
            contentDescriptors: $this->contentDescriptors,
            errors: $this->errors,
            examples: $this->examples,
            examplePairings: $this->examplePairings,
            links: $this->links,
            tags: $this->tags,
            resources: $this->resources,
        );
    }

    /**
     * Add a content descriptor component.
     *
     * @param string       $name       Descriptor identifier
     * @param ArgumentData $descriptor Content descriptor definition
     *
     * @return self New instance with the descriptor added
     */
    public function withContentDescriptor(string $name, ArgumentData $descriptor): self
    {
        $descriptors = $this->contentDescriptors ?? [];
        $descriptors[$name] = $descriptor;

        return new self(
            schemas: $this->schemas,
            contentDescriptors: $descriptors,
            errors: $this->errors,
            examples: $this->examples,
            examplePairings: $this->examplePairings,
            links: $this->links,
            tags: $this->tags,
            resources: $this->resources,
        );
    }

    /**
     * Add an error component.
     *
     * @param string              $name  Error identifier
     * @param ErrorDefinitionData $error Error definition
     *
     * @return self New instance with the error added
     */
    public function withError(string $name, ErrorDefinitionData $error): self
    {
        $errors = $this->errors ?? [];
        $errors[$name] = $error;

        return new self(
            schemas: $this->schemas,
            contentDescriptors: $this->contentDescriptors,
            errors: $errors,
            examples: $this->examples,
            examplePairings: $this->examplePairings,
            links: $this->links,
            tags: $this->tags,
            resources: $this->resources,
        );
    }

    /**
     * Add a resource component.
     *
     * @param string       $name     Resource type identifier
     * @param ResourceData $resource Resource definition
     *
     * @return self New instance with the resource added
     */
    public function withResource(string $name, ResourceData $resource): self
    {
        $resources = $this->resources ?? [];
        $resources[$name] = $resource;

        return new self(
            schemas: $this->schemas,
            contentDescriptors: $this->contentDescriptors,
            errors: $this->errors,
            examples: $this->examples,
            examplePairings: $this->examplePairings,
            links: $this->links,
            tags: $this->tags,
            resources: $resources,
        );
    }

    /**
     * Merge another ComponentsData instance into this one.
     *
     * @param self $other Component data to merge
     *
     * @return self New instance with merged components
     */
    public function merge(self $other): self
    {
        return new self(
            schemas: array_merge($this->schemas ?? [], $other->schemas ?? []),
            contentDescriptors: array_merge($this->contentDescriptors ?? [], $other->contentDescriptors ?? []),
            errors: array_merge($this->errors ?? [], $other->errors ?? []),
            examples: array_merge($this->examples ?? [], $other->examples ?? []),
            examplePairings: array_merge($this->examplePairings ?? [], $other->examplePairings ?? []),
            links: array_merge($this->links ?? [], $other->links ?? []),
            tags: array_merge($this->tags ?? [], $other->tags ?? []),
            resources: array_merge($this->resources ?? [], $other->resources ?? []),
        );
    }
}

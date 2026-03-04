<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Data\Configuration;

use Cline\Forrst\Data\AbstractData;
use Cline\Forrst\Exceptions\EmptyArrayException;
use Cline\Forrst\Exceptions\InvalidConfigurationException;
use Cline\Forrst\Exceptions\InvalidFieldTypeException;
use InvalidArgumentException;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Present;
use Spatie\LaravelData\DataCollection;

use function base_path;
use function class_exists;
use function config;
use function is_string;
use function realpath;
use function sprintf;
use function str_contains;
use function str_starts_with;

/**
 * Main configuration data for the Forrst package.
 *
 * Holds the complete configuration structure including namespace mappings,
 * file paths, resource definitions, and server configurations. This data
 * object is populated from the rpc.php configuration file and used
 * throughout the application lifecycle.
 *
 * Serves as the central configuration container that defines how the Forrst
 * server discovers functions, where they are located in the filesystem,
 * and what endpoints are available. Multiple servers can be configured to
 * provide different API versions or isolated function sets.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/
 */
final class ConfigurationData extends AbstractData
{
    /**
     * Create a new configuration data instance.
     *
     * @param array<string, string>           $namespaces Namespace configuration mappings that define
     *                                                    where RPC components are located. Maps namespace
     *                                                    prefixes to base namespaces for automatic class
     *                                                    discovery during server initialization (e.g.,
     *                                                    'functions' => 'App\\Rpc\\Functions').
     * @param array<string, string>           $paths      File system path mappings defining directory
     *                                                    locations for RPC components. Used for scanning
     *                                                    and discovering classes during server bootstrap
     *                                                    and function registration (e.g., 'functions' =>
     *                                                    app_path('Rpc/Functions')).
     * @param array<string, mixed>            $resources  Resource transformation configuration defining
     *                                                    how data models are converted to standardized
     *                                                    JSON representations. Currently unused but
     *                                                    reserved for future resource mapping features.
     * @param DataCollection<int, ServerData> $servers    Collection of server configuration objects
     *                                                    defining available RPC endpoints, their
     *                                                    routes, middleware stacks, and capabilities.
     *                                                    Each server represents a separate endpoint
     *                                                    with its own function set and configuration.
     */
    public function __construct(
        public readonly array $namespaces,
        public readonly array $paths,
        #[Present()]
        public readonly array $resources,
        #[DataCollectionOf(ServerData::class)]
        public readonly DataCollection $servers,
    ) {
        $this->validateConfiguration();
    }

    /**
     * Create configuration from array data.
     *
     * @param  array<string, mixed> $data Configuration array
     * @return self                 Configured instance
     */
    public static function createFromArray(array $data): self
    {
        return new self(
            namespaces: $data['namespaces'] ?? [],
            paths: $data['paths'] ?? [],
            resources: $data['resources'] ?? [],
            servers: DataCollection::create(
                ServerData::class,
                $data['servers'] ?? [],
            ),
        );
    }

    /**
     * Create configuration from config file.
     *
     * @param  string $configKey The config key (e.g., 'rpc')
     * @return self   Configured instance
     */
    public static function createFromConfig(string $configKey = 'rpc'): self
    {
        $config = config($configKey, []);

        return self::createFromArray($config);
    }

    /**
     * Validate configuration data.
     *
     * @throws InvalidArgumentException When configuration is invalid
     */
    private function validateConfiguration(): void
    {
        // Validate namespaces
        if ($this->namespaces === []) {
            throw EmptyArrayException::forField('namespaces');
        }

        foreach ($this->namespaces as $key => $namespace) {
            if (!is_string($key) || !is_string($namespace)) {
                throw InvalidFieldTypeException::forField(
                    'namespaces',
                    'array<string, string>',
                    $this->namespaces,
                );
            }

            if (!class_exists($namespace) && !str_starts_with($namespace, 'App\\')) {
                throw InvalidConfigurationException::forKey(
                    $key,
                    sprintf('Invalid namespace "%s"', $namespace),
                );
            }
        }

        // Validate paths
        if ($this->paths === []) {
            throw EmptyArrayException::forField('paths');
        }

        foreach ($this->paths as $key => $path) {
            if (!is_string($key) || !is_string($path)) {
                throw InvalidFieldTypeException::forField(
                    'paths',
                    'array<string, string>',
                    $this->paths,
                );
            }

            // Check for path traversal attempts
            if (str_contains($path, '..')) {
                throw InvalidConfigurationException::forKey(
                    $key,
                    sprintf('Path traversal detected in path "%s"', $path),
                );
            }

            // Normalize and validate path exists
            $realPath = realpath($path);

            if ($realPath === false) {
                throw InvalidConfigurationException::forKey(
                    $key,
                    sprintf('Path "%s" does not exist', $path),
                );
            }

            // Ensure path is within application root
            $appPath = base_path();

            if (!str_starts_with($realPath, $appPath)) {
                throw InvalidConfigurationException::forKey(
                    $key,
                    sprintf('Path "%s" is outside application root', $path),
                );
            }
        }

        // Validate servers
        if ($this->servers->isEmpty()) {
            throw InvalidConfigurationException::forKey(
                'servers',
                'At least one server must be configured',
            );
        }
    }
}

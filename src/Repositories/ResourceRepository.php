<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Repositories;

use Cline\Forrst\Contracts\ResourceInterface;
use Cline\Forrst\Exceptions\InternalErrorException;
use DomainException;
use Illuminate\Database\Eloquent\Model;

use function sprintf;
use function throw_if;

/**
 * Global registry for model-to-resource transformer mappings.
 *
 * Provides static storage and lookup of the associations between Eloquent model
 * classes and their corresponding ResourceInterface implementations. Enables
 * automatic transformation of models into Forrst resource objects by resolving
 * the appropriate transformer class at runtime.
 *
 * Used during response serialization to convert Eloquent models into the
 * standardized resource object format required by the Forrst protocol.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/resource-objects
 */
final class ResourceRepository
{
    /**
     * Static mapping of model class names to resource class names.
     *
     * Keys are fully qualified model class names, values are resource class names
     * implementing ResourceInterface.
     *
     * @var array<class-string, class-string<ResourceInterface>>
     */
    private static array $resources = [];

    /**
     * Returns all registered model-to-resource mappings.
     *
     * @return array<class-string, class-string<ResourceInterface>> Mappings indexed by model class name
     */
    public static function all(): array
    {
        return self::$resources;
    }

    /**
     * Resolves and instantiates the resource transformer for a model instance.
     *
     * Looks up the registered resource class for the model's class name, instantiates
     * it with the model, and returns the configured transformer ready for serialization.
     *
     * @param Model $model Eloquent model instance to transform
     *
     * @throws InternalErrorException When no resource is registered for the model class
     *                                or the registered class is not a ResourceInterface
     *
     * @return ResourceInterface Resource transformer wrapping the model
     */
    public static function get(Model $model): ResourceInterface
    {
        $resourceClass = self::$resources[$model::class] ?? null;

        throw_if($resourceClass === null, InternalErrorException::create(
            new DomainException(sprintf('Resource for model [%s] not found.', $model)),
        ));

        $resource = new $resourceClass($model);

        /** @phpstan-ignore instanceof.alwaysTrue */
        if (!$resource instanceof ResourceInterface) {
            throw InternalErrorException::create(
                new DomainException(sprintf('Resource for model [%s] not found.', $model)),
            );
        }

        return $resource;
    }

    /**
     * Removes a model-to-resource mapping from the registry.
     *
     * @param class-string $model Fully qualified model class name to unregister
     */
    public static function forget(string $model): void
    {
        unset(self::$resources[$model]);
    }

    /**
     * Registers a resource transformer for a specific model class.
     *
     * @param class-string                    $model    Fully qualified Eloquent model class name
     * @param class-string<ResourceInterface> $resource Fully qualified resource transformer class name
     */
    public static function register(string $model, string $resource): void
    {
        self::$resources[$model] = $resource;
    }
}

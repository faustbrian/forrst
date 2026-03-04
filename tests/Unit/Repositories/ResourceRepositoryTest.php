<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Repositories;

use Cline\Forrst\Contracts\ResourceInterface;
use Cline\Forrst\Exceptions\InternalErrorException;
use Cline\Forrst\Repositories\ResourceRepository;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Support\Models\Post;
use Tests\Support\Models\User;
use Tests\Support\Resources\InvalidResource;
use Tests\Support\Resources\PostResource;
use Tests\Support\Resources\UserResource;
use Tests\TestCase;

use function array_keys;

/**
 * Tests for ResourceRepository functionality.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @internal
 */
#[CoversClass(ResourceRepository::class)]
#[Small()]
final class ResourceRepositoryTest extends TestCase
{
    /**
     * Set up the test environment.
     * Clears all registered resources to ensure test isolation.
     */
    #[Override()]
    protected function setUp(): void
    {
        parent::setUp();

        // Clear all registered resources for test isolation
        $allResources = ResourceRepository::all();

        foreach (array_keys($allResources) as $model) {
            ResourceRepository::forget($model);
        }
    }

    #[Test()]
    #[TestDox('Registers and retrieves a resource successfully')]
    #[Group('happy-path')]
    public function registers_and_retrieves_resource_successfully(): void
    {
        // Arrange
        ResourceRepository::register(User::class, UserResource::class);
        $user = new User();

        // Act
        $resource = ResourceRepository::get($user);

        // Assert
        $this->assertInstanceOf(ResourceInterface::class, $resource);
        $this->assertInstanceOf(UserResource::class, $resource);
    }

    #[Test()]
    #[TestDox('Retrieves all registered resources')]
    #[Group('happy-path')]
    public function retrieves_all_registered_resources(): void
    {
        // Arrange
        ResourceRepository::register(Post::class, PostResource::class);
        ResourceRepository::register(User::class, UserResource::class);

        // Act
        $resources = ResourceRepository::all();

        // Assert
        $this->assertArrayHasKey(Post::class, $resources);
        $this->assertArrayHasKey(User::class, $resources);
        $this->assertEquals(PostResource::class, $resources[Post::class]);
        $this->assertEquals(UserResource::class, $resources[User::class]);
    }

    #[Test()]
    #[TestDox('Throws exception when resource is not found for model')]
    #[Group('sad-path')]
    public function throws_exception_when_resource_not_found_for_model(): void
    {
        // Arrange
        $user = new User();

        // Act
        $this->expectException(InternalErrorException::class);
        $this->expectExceptionMessage('Internal error');

        // Assert
        ResourceRepository::get($user);
    }

    #[Test()]
    #[TestDox('Throws exception when registered class does not implement ResourceInterface')]
    #[Group('sad-path')]
    public function throws_exception_when_registered_class_does_not_implement_resource_interface(): void
    {
        // Arrange
        ResourceRepository::register(User::class, InvalidResource::class);
        $user = new User();

        // Act
        $this->expectException(InternalErrorException::class);
        $this->expectExceptionMessage('Internal error');

        // Assert
        ResourceRepository::get($user);
    }

    #[Test()]
    #[TestDox('Forget removes a specific resource from registry')]
    #[Group('edge-case')]
    public function forget_removes_specific_resource_from_registry(): void
    {
        // Arrange
        ResourceRepository::register(User::class, UserResource::class);
        ResourceRepository::register(Post::class, PostResource::class);

        // Act
        ResourceRepository::forget(User::class);
        $resources = ResourceRepository::all();

        // Assert
        $this->assertArrayNotHasKey(User::class, $resources);
        $this->assertArrayHasKey(Post::class, $resources);
    }

    #[Test()]
    #[TestDox('Register overwrites existing resource mapping')]
    #[Group('edge-case')]
    public function register_overwrites_existing_resource_mapping(): void
    {
        // Arrange
        ResourceRepository::register(User::class, UserResource::class);

        // Act
        ResourceRepository::register(User::class, PostResource::class);
        $resources = ResourceRepository::all();

        // Assert
        $this->assertEquals(PostResource::class, $resources[User::class]);
    }

    #[Test()]
    #[TestDox('All returns empty array when no resources registered')]
    #[Group('edge-case')]
    public function all_returns_empty_array_when_no_resources_registered(): void
    {
        // Arrange - already cleared in setUp()

        // Act
        $resources = ResourceRepository::all();

        // Assert
        $this->assertIsArray($resources);
        $this->assertEmpty($resources);
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Resources;

use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\QueryBuilders\QueryBuilder;
use Cline\Forrst\Repositories\ResourceRepository;
use Cline\Forrst\Resources\AbstractModelResource;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Support\Models\User;
use Tests\Support\Resources\EdgeCaseResource;
use Tests\Support\Resources\ResourceWithoutRelationConflict;
use Tests\Support\Resources\ResourceWithRelationConflict;
use Tests\Support\Resources\TestResource;
use Tests\Support\Resources\UserResource;
use Tests\TestCase;

use function collect;
use function now;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
#[CoversClass(AbstractModelResource::class)]
#[Small()]
final class AbstractModelResourceTest extends TestCase
{
    private User $user;

    private UserResource $resource;

    #[Override()]
    protected function setUp(): void
    {
        parent::setUp();

        // Arrange
        ResourceRepository::register(User::class, UserResource::class);

        $this->user = new User();
        $this->user->id = 1;
        $this->user->name = 'John Doe';
        $this->user->created_at = now();
        $this->user->updated_at = now();

        $this->resource = new UserResource($this->user);
    }

    #[Test()]
    #[TestDox('Creates resource from model with proper ID and type')]
    #[Group('happy-path')]
    public function creates_resource_from_model(): void
    {
        // Arrange
        // Already done in setUp

        // Act
        $id = $this->resource->getId();
        $type = $this->resource->getType();

        // Assert
        $this->assertEquals('1', $id);
        $this->assertSame('user', $type);
    }

    #[Test()]
    #[TestDox('Gets model class name from resource class')]
    #[Group('happy-path')]
    public function gets_model_class(): void
    {
        // Arrange
        // Resource class is UserResource

        // Act
        $modelClass = UserResource::getModel();

        // Assert
        $this->assertSame(User::class, $modelClass);
    }

    #[Test()]
    #[TestDox('Gets model class for resource without custom getModel override')]
    #[Group('edge-case')]
    public function gets_model_class_without_override(): void
    {
        // Arrange
        $user = new User();
        new TestResource($user);

        // Act
        $modelClass = TestResource::getModel();

        // Assert
        $this->assertSame('App\\Models\\Test', $modelClass);
    }

    #[Test()]
    #[TestDox('Gets fields configuration from resource')]
    #[Group('happy-path')]
    public function gets_fields_configuration(): void
    {
        // Arrange
        // UserResource has fields defined

        // Act
        $fields = UserResource::getFields();

        // Assert
        $this->assertArrayHasKey('self', $fields);
        $this->assertContains('id', $fields['self']);
        $this->assertContains('name', $fields['self']);
    }

    #[Test()]
    #[TestDox('Gets filters configuration from resource')]
    #[Group('happy-path')]
    public function gets_filters_configuration(): void
    {
        // Arrange
        // UserResource has filters defined

        // Act
        $filters = UserResource::getFilters();

        // Assert
        $this->assertArrayHasKey('self', $filters);
    }

    #[Test()]
    #[TestDox('Gets relationships configuration from resource')]
    #[Group('happy-path')]
    public function gets_relationships_configuration(): void
    {
        // Arrange
        // UserResource has relationships defined

        // Act
        $relationships = UserResource::getRelationships();

        // Assert
        $this->assertArrayHasKey('self', $relationships);
        $this->assertContains('posts', $relationships['self']);
    }

    #[Test()]
    #[TestDox('Gets sorts configuration from resource')]
    #[Group('happy-path')]
    public function gets_sorts_configuration(): void
    {
        // Arrange
        // UserResource has sorts defined

        // Act
        $sorts = UserResource::getSorts();

        // Assert
        $this->assertArrayHasKey('self', $sorts);
        $this->assertContains('created_at', $sorts['self']);
    }

    #[Test()]
    #[TestDox('Gets attributes without ID and relations')]
    #[Group('happy-path')]
    public function gets_attributes_without_id_and_relations(): void
    {
        // Arrange
        $this->user->posts = collect([]); // Add relation to be filtered

        // Act
        $attributes = $this->resource->getAttributes();

        // Assert
        $this->assertArrayHasKey('name', $attributes);
        $this->assertArrayNotHasKey('id', $attributes);
        $this->assertArrayNotHasKey('posts', $attributes);
    }

    #[Test()]
    #[TestDox('Filters out relation from attributes when present')]
    #[Group('edge-case')]
    public function filters_relation_from_attributes_when_present(): void
    {
        // Arrange
        $user = new User();
        $user->id = 2;
        $user->name = 'Jane Doe';
        $user->posts = 'some_value'; // Attribute with same name as relation

        // Create a resource with relation that matches an attribute name
        $resource = new ResourceWithRelationConflict($user);
        $resource->setRelations(['posts' => []]); // Has a relation named 'posts'

        // Act
        $attributes = $resource->getAttributes();

        // Assert
        $this->assertArrayNotHasKey('posts', $attributes); // Should be filtered out
        $this->assertArrayHasKey('name', $attributes);
    }

    #[Test()]
    #[TestDox('Does not filter attributes when no matching relations')]
    #[Group('edge-case')]
    public function does_not_filter_attributes_when_no_matching_relations(): void
    {
        // Arrange
        $user = new User();
        $user->id = 3;
        $user->name = 'Bob Smith';
        $user->email = 'bob@example.com';

        // Resource with no matching relation names
        $resource = new ResourceWithoutRelationConflict($user);
        $resource->setRelations(['comments' => []]); // Relation that doesn't match any attribute

        // Act
        $attributes = $resource->getAttributes();

        // Assert
        $this->assertArrayHasKey('name', $attributes);
        $this->assertArrayHasKey('email', $attributes);
        $this->assertArrayNotHasKey('comments', $attributes);
    }

    #[Test()]
    #[TestDox('Gets model table name')]
    #[Group('happy-path')]
    public function gets_model_table_name(): void
    {
        // Arrange
        // UserResource model has 'users' table

        // Act
        $table = UserResource::getModelTable();

        // Assert
        $this->assertSame('users', $table);
    }

    #[Test()]
    #[TestDox('Gets model type as singular table name')]
    #[Group('happy-path')]
    public function gets_model_type(): void
    {
        // Arrange
        // UserResource model has 'users' table

        // Act
        $type = UserResource::getModelType();

        // Assert
        $this->assertSame('user', $type);
    }

    #[Test()]
    #[TestDox('Creates query builder with request parameters')]
    #[Group('happy-path')]
    public function creates_query_builder_with_request_parameters(): void
    {
        // Arrange
        $request = RequestObjectData::asRequest('urn:app:forrst:fn:users:list');

        // Act
        $queryBuilder = UserResource::query($request);

        // Assert
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
    }

    #[Test()]
    #[TestDox('Creates query builder with empty parameters')]
    #[Group('edge-case')]
    public function creates_query_builder_with_empty_parameters(): void
    {
        // Arrange
        $request = RequestObjectData::asRequest('urn:app:forrst:fn:users:list', []);

        // Act
        $queryBuilder = UserResource::query($request);

        // Assert
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
    }

    #[Test()]
    #[TestDox('Gets loaded relations from model')]
    #[Group('happy-path')]
    public function gets_loaded_relations_from_model(): void
    {
        // Arrange
        $posts = collect(['post1', 'post2']);
        $this->user->setRelation('posts', $posts);

        // Act
        $relations = $this->resource->getRelations();

        // Assert
        $this->assertArrayHasKey('posts', $relations);
        $this->assertEquals($posts, $relations['posts']);
    }

    #[Test()]
    #[TestDox('Returns empty array when no relations loaded')]
    #[Group('edge-case')]
    public function returns_empty_array_when_no_relations_loaded(): void
    {
        // Arrange
        $user = new User();
        $resource = new UserResource($user);

        // Act
        $relations = $resource->getRelations();

        // Assert
        $this->assertEmpty($relations);
    }

    #[Test()]
    #[TestDox('Handles resource class name edge cases')]
    #[Group('edge-case')]
    #[DataProvider('provideHandles_resource_class_name_edge_casesCases')]
    public function handles_resource_class_name_edge_cases(string $className, string $expectedModel): void
    {
        // Arrange
        EdgeCaseResource::setTestClassName($className);

        // Act
        $modelClass = EdgeCaseResource::getModel();

        // Assert
        $this->assertSame($expectedModel, $modelClass);
    }

    public static function provideHandles_resource_class_name_edge_casesCases(): iterable
    {
        yield 'standard resource name' => ['UserResource', 'App\\Models\\User'];

        yield 'resource without suffix' => ['User', 'App\\Models\\User'];

        yield 'resource word alone' => ['Resource', 'App\\Models\\'];

        yield 'multiple resource words' => ['ResourceResource', 'App\\Models\\Resource'];

        yield 'nested resource name' => ['AdminUserResource', 'App\\Models\\AdminUser'];
    }
}

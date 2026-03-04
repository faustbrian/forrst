<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Resources\AbstractDataResource;
use Tests\Support\Fixtures\CustomEntityData;
use Tests\Support\Fixtures\DataWithoutId;
use Tests\Support\Fixtures\EmptyAttributesData;
use Tests\Support\Fixtures\PostsData;
use Tests\Support\Fixtures\UserData;
use Tests\Support\Resources\CustomEntityDataResource;
use Tests\Support\Resources\DataWithoutIdResource;
use Tests\Support\Resources\EmptyAttributesDataResource;
use Tests\Support\Resources\PostsDataResource;
use Tests\Support\Resources\UserDataResource;

describe('AbstractDataResource', function (): void {
    describe('Happy Paths', function (): void {
        describe('__construct()', function (): void {
            test('creates resource with UserData object', function (): void {
                // Arrange
                $data = new UserData(
                    id: 1,
                    name: 'John Doe',
                    email: 'john@example.com',
                );

                // Act
                $resource = new UserDataResource($data);

                // Assert
                expect($resource)->toBeInstanceOf(AbstractDataResource::class);
            });

            test('creates resource with PostsData object', function (): void {
                // Arrange
                $data = new PostsData(
                    id: 42,
                    title: 'Test Post',
                    content: 'This is test content',
                );

                // Act
                $resource = new PostsDataResource($data);

                // Assert
                expect($resource)->toBeInstanceOf(AbstractDataResource::class);
            });

            test('creates resource with CustomEntityData object', function (): void {
                // Arrange
                $data = new CustomEntityData(
                    id: 'uuid-123',
                    type: 'entity',
                    metadata: ['key' => 'value'],
                );

                // Act
                $resource = new CustomEntityDataResource($data);

                // Assert
                expect($resource)->toBeInstanceOf(AbstractDataResource::class);
            });

            test('creates resource with Data object without id property', function (): void {
                // Arrange
                $data = new DataWithoutId(
                    name: 'test',
                    value: 'data',
                );

                // Act
                $resource = new DataWithoutIdResource($data);

                // Assert
                expect($resource)->toBeInstanceOf(AbstractDataResource::class);
            });

            test('creates resource with minimal Data object', function (): void {
                // Arrange
                $data = new EmptyAttributesData(id: 999);

                // Act
                $resource = new EmptyAttributesDataResource($data);

                // Assert
                expect($resource)->toBeInstanceOf(AbstractDataResource::class);
            });
        });

        describe('getType()', function (): void {
            test('derives type from UserData class name', function (): void {
                // Arrange
                $data = new UserData(
                    id: 1,
                    name: 'John Doe',
                    email: 'john@example.com',
                );
                $resource = new UserDataResource($data);

                // Act
                $type = $resource->getType();

                // Assert
                expect($type)->toBe('User');
            });

            test('derives singular type from plural PostsData class name', function (): void {
                // Arrange
                $data = new PostsData(
                    id: 1,
                    title: 'Test Post',
                    content: 'Content',
                );
                $resource = new PostsDataResource($data);

                // Act
                $type = $resource->getType();

                // Assert
                expect($type)->toBe('Post');
            });

            test('derives type from compound CustomEntityData class name', function (): void {
                // Arrange
                $data = new CustomEntityData(
                    id: '123',
                    type: 'entity',
                    metadata: [],
                );
                $resource = new CustomEntityDataResource($data);

                // Act
                $type = $resource->getType();

                // Assert
                expect($type)->toBe('CustomEntity');
            });

            test('removes Data suffix correctly', function (): void {
                // Arrange
                $data = new UserData(
                    id: 1,
                    name: 'Test',
                    email: 'test@example.com',
                );
                $resource = new UserDataResource($data);

                // Act
                $type = $resource->getType();

                // Assert
                expect($type)->not->toContain('Data');
            });
        });

        describe('getId()', function (): void {
            test('returns integer id as string from UserData', function (): void {
                // Arrange
                $data = new UserData(
                    id: 123,
                    name: 'John Doe',
                    email: 'john@example.com',
                );
                $resource = new UserDataResource($data);

                // Act
                $id = $resource->getId();

                // Assert
                expect($id)->toBe('123');
                expect($id)->toBeString();
            });

            test('returns string id from CustomEntityData', function (): void {
                // Arrange
                $data = new CustomEntityData(
                    id: 'uuid-abc-123',
                    type: 'entity',
                    metadata: [],
                );
                $resource = new CustomEntityDataResource($data);

                // Act
                $id = $resource->getId();

                // Assert
                expect($id)->toBe('uuid-abc-123');
            });

            test('returns id from PostsData', function (): void {
                // Arrange
                $data = new PostsData(
                    id: 42,
                    title: 'Test',
                    content: 'Content',
                );
                $resource = new PostsDataResource($data);

                // Act
                $id = $resource->getId();

                // Assert
                expect($id)->toBe('42');
            });

            test('returns zero as string for Data with id zero', function (): void {
                // Arrange
                $data = new EmptyAttributesData(id: 0);
                $resource = new EmptyAttributesDataResource($data);

                // Act
                $id = $resource->getId();

                // Assert
                expect($id)->toBe('0');
            });
        });

        describe('getAttributes()', function (): void {
            test('returns all UserData properties as array', function (): void {
                // Arrange
                $data = new UserData(
                    id: 1,
                    name: 'John Doe',
                    email: 'john@example.com',
                );
                $resource = new UserDataResource($data);

                // Act
                $attributes = $resource->getAttributes();

                // Assert
                expect($attributes)->toBe([
                    'id' => 1,
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ]);
            });

            test('returns all PostsData properties as array', function (): void {
                // Arrange
                $data = new PostsData(
                    id: 42,
                    title: 'Test Post',
                    content: 'This is the content',
                );
                $resource = new PostsDataResource($data);

                // Act
                $attributes = $resource->getAttributes();

                // Assert
                expect($attributes)->toBe([
                    'id' => 42,
                    'title' => 'Test Post',
                    'content' => 'This is the content',
                ]);
            });

            test('returns CustomEntityData with array metadata', function (): void {
                // Arrange
                $metadata = ['key1' => 'value1', 'key2' => 'value2'];
                $data = new CustomEntityData(
                    id: 'entity-123',
                    type: 'custom',
                    metadata: $metadata,
                );
                $resource = new CustomEntityDataResource($data);

                // Act
                $attributes = $resource->getAttributes();

                // Assert
                expect($attributes)->toBe([
                    'id' => 'entity-123',
                    'type' => 'custom',
                    'metadata' => $metadata,
                ]);
            });

            test('returns minimal attributes for simple Data object', function (): void {
                // Arrange
                $data = new EmptyAttributesData(id: 999);
                $resource = new EmptyAttributesDataResource($data);

                // Act
                $attributes = $resource->getAttributes();

                // Assert
                expect($attributes)->toBe(['id' => 999]);
            });

            test('returns correct array structure', function (): void {
                // Arrange
                $data = new UserData(
                    id: 1,
                    name: 'Test',
                    email: 'test@example.com',
                );
                $resource = new UserDataResource($data);

                // Act
                $attributes = $resource->getAttributes();

                // Assert
                expect($attributes)->toBeArray();
                expect($attributes)->toHaveKeys(['id', 'name', 'email']);
            });
        });

        describe('getRelations()', function (): void {
            test('returns empty array for UserDataResource', function (): void {
                // Arrange
                $data = new UserData(
                    id: 1,
                    name: 'John Doe',
                    email: 'john@example.com',
                );
                $resource = new UserDataResource($data);

                // Act
                $relations = $resource->getRelations();

                // Assert
                expect($relations)->toBe([]);
            });

            test('returns empty array for PostsDataResource', function (): void {
                // Arrange
                $data = new PostsData(
                    id: 1,
                    title: 'Test',
                    content: 'Content',
                );
                $resource = new PostsDataResource($data);

                // Act
                $relations = $resource->getRelations();

                // Assert
                expect($relations)->toBe([]);
            });

            test('returns empty array for CustomEntityDataResource', function (): void {
                // Arrange
                $data = new CustomEntityData(
                    id: '123',
                    type: 'entity',
                    metadata: [],
                );
                $resource = new CustomEntityDataResource($data);

                // Act
                $relations = $resource->getRelations();

                // Assert
                expect($relations)->toBe([]);
            });

            test('returns array type', function (): void {
                // Arrange
                $data = new UserData(
                    id: 1,
                    name: 'Test',
                    email: 'test@example.com',
                );
                $resource = new UserDataResource($data);

                // Act
                $relations = $resource->getRelations();

                // Assert
                expect($relations)->toBeArray();
            });
        });

        describe('toArray()', function (): void {
            test('returns complete resource structure', function (): void {
                // Arrange
                $data = new UserData(
                    id: 1,
                    name: 'John Doe',
                    email: 'john@example.com',
                );
                $resource = new UserDataResource($data);

                // Act
                $array = $resource->toArray();

                // Assert
                expect($array)->toBe([
                    'type' => 'User',
                    'id' => '1',
                    'attributes' => [
                        'id' => 1,
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                    ],
                ]);
            });

            test('includes all required keys', function (): void {
                // Arrange
                $data = new UserData(
                    id: 1,
                    name: 'Test',
                    email: 'test@example.com',
                );
                $resource = new UserDataResource($data);

                // Act
                $array = $resource->toArray();

                // Assert
                expect($array)->toHaveKeys(['type', 'id', 'attributes']);
            });
        });
    });

    describe('Edge Cases', function (): void {
        describe('getType()', function (): void {
            test('handles Data class where Data is at beginning of name', function (): void {
                // Arrange - DataWithoutId has 'Data' at beginning, so beforeLast returns empty string
                $data = new DataWithoutId(
                    name: 'test',
                    value: 'value',
                );
                $resource = new DataWithoutIdResource($data);

                // Act
                $type = $resource->getType();

                // Assert
                expect($type)->toBe('');
            });
        });

        describe('getId()', function (): void {
            test('returns empty string when Data object has no id property', function (): void {
                // Arrange
                $data = new DataWithoutId(
                    name: 'test',
                    value: 'data',
                );
                $resource = new DataWithoutIdResource($data);

                // Act
                $id = $resource->getId();

                // Assert
                expect($id)->toBe('');
            });

            test('handles large integer id values', function (): void {
                // Arrange
                $data = new UserData(
                    id: 9_999_999_999,
                    name: 'Test',
                    email: 'test@example.com',
                );
                $resource = new UserDataResource($data);

                // Act
                $id = $resource->getId();

                // Assert
                expect($id)->toBe('9999999999');
            });

            test('handles negative integer id values', function (): void {
                // Arrange
                $data = new EmptyAttributesData(id: -1);
                $resource = new EmptyAttributesDataResource($data);

                // Act
                $id = $resource->getId();

                // Assert
                expect($id)->toBe('-1');
            });
        });

        describe('getAttributes()', function (): void {
            test('handles Data with empty array metadata', function (): void {
                // Arrange
                $data = new CustomEntityData(
                    id: 'test',
                    type: 'empty',
                    metadata: [],
                );
                $resource = new CustomEntityDataResource($data);

                // Act
                $attributes = $resource->getAttributes();

                // Assert
                expect($attributes['metadata'])->toBe([]);
            });

            test('handles Data with nested array structures', function (): void {
                // Arrange
                $nestedData = [
                    'level1' => [
                        'level2' => [
                            'level3' => 'value',
                        ],
                    ],
                ];
                $data = new CustomEntityData(
                    id: 'nested',
                    type: 'complex',
                    metadata: $nestedData,
                );
                $resource = new CustomEntityDataResource($data);

                // Act
                $attributes = $resource->getAttributes();

                // Assert
                expect($attributes['metadata'])->toBe($nestedData);
            });

            test('handles Data with special characters in string values', function (): void {
                // Arrange
                $data = new UserData(
                    id: 1,
                    name: 'John "The Boss" O\'Reilly',
                    email: 'john+test@example.com',
                );
                $resource = new UserDataResource($data);

                // Act
                $attributes = $resource->getAttributes();

                // Assert
                expect($attributes['name'])->toBe('John "The Boss" O\'Reilly');
                expect($attributes['email'])->toBe('john+test@example.com');
            });

            test('handles Data with unicode characters', function (): void {
                // Arrange
                $data = new UserData(
                    id: 1,
                    name: 'José García 日本語',
                    email: 'jose@example.com',
                );
                $resource = new UserDataResource($data);

                // Act
                $attributes = $resource->getAttributes();

                // Assert
                expect($attributes['name'])->toBe('José García 日本語');
            });

            test('handles Data with empty string values', function (): void {
                // Arrange
                $data = new PostsData(
                    id: 1,
                    title: '',
                    content: '',
                );
                $resource = new PostsDataResource($data);

                // Act
                $attributes = $resource->getAttributes();

                // Assert
                expect($attributes['title'])->toBe('');
                expect($attributes['content'])->toBe('');
            });
        });

        describe('toArray()', function (): void {
            test('handles resource with no id property', function (): void {
                // Arrange
                $data = new DataWithoutId(
                    name: 'test',
                    value: 'data',
                );
                $resource = new DataWithoutIdResource($data);

                // Act
                $array = $resource->toArray();

                // Assert
                expect($array)->toHaveKeys(['type', 'id', 'attributes']);
                expect($array['id'])->toBe('');
            });

            test('handles resource with minimal data', function (): void {
                // Arrange
                $data = new EmptyAttributesData(id: 1);
                $resource = new EmptyAttributesDataResource($data);

                // Act
                $array = $resource->toArray();

                // Assert
                expect($array['attributes'])->toBe(['id' => 1]);
            });
        });
    });
});

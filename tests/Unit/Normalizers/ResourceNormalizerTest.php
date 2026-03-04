<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\ResourceObjectData;
use Cline\Forrst\Normalizers\ResourceNormalizer;
use Cline\Forrst\Repositories\ResourceRepository;
use Tests\Support\Models\Post;
use Tests\Support\Models\User;
use Tests\Support\Resources\PostResource;
use Tests\Support\Resources\UserResource;

describe('ResourceNormalizer', function (): void {
    beforeEach(function (): void {
        ResourceRepository::register(User::class, UserResource::class);
        ResourceRepository::register(Post::class, PostResource::class);
    });

    describe('Happy Paths', function (): void {
        test('normalizes resource with all fields', function (): void {
            // Arrange
            $user = User::query()->create([
                'name' => 'John Doe',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Act
            $resource = ResourceRepository::get($user);
            $normalized = ResourceNormalizer::normalize($resource);

            // Assert
            expect($normalized)->toBeInstanceOf(ResourceObjectData::class);
            expect($normalized->type)->toBe('user');
            expect($normalized->id)->toBe((string) $user->id);
            expect($normalized->attributes)->toHaveKey('name');
        });

        test('normalizes collection of resources', function (): void {
            // Arrange
            $user1 = User::query()->create(['name' => 'User 1', 'created_at' => now(), 'updated_at' => now()]);
            $user2 = User::query()->create(['name' => 'User 2', 'created_at' => now(), 'updated_at' => now()]);

            // Act
            $collection = collect([$user1, $user2])->map(ResourceRepository::get(...));
            $normalized = $collection->map(ResourceNormalizer::normalize(...));

            // Assert
            expect($normalized)->toHaveCount(2);
            expect($normalized->first())->toBeInstanceOf(ResourceObjectData::class);
        });

        test('normalizes resource with one-to-many relationships', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'John Doe', 'created_at' => now(), 'updated_at' => now()]);
            $post1 = $user->posts()->create(['name' => 'First Post', 'created_at' => now(), 'updated_at' => now()]);
            $post2 = $user->posts()->create(['name' => 'Second Post', 'created_at' => now(), 'updated_at' => now()]);

            $user->load('posts');

            // Act
            $resource = ResourceRepository::get($user);
            $normalized = ResourceNormalizer::normalize($resource);

            // Assert
            expect($normalized)->toBeInstanceOf(ResourceObjectData::class);
            expect($normalized->relationships)->toHaveKey('posts');
            expect($normalized->relationships['posts'])->toBeArray();
            expect($normalized->relationships['posts'])->toHaveCount(2);
        });

        test('normalizes resource with one-to-one relationship', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Jane Doe', 'created_at' => now(), 'updated_at' => now()]);
            $post = $user->posts()->create(['name' => 'Single Post', 'created_at' => now(), 'updated_at' => now()]);

            $post->load('user');

            // Act
            $resource = ResourceRepository::get($post);
            $normalized = ResourceNormalizer::normalize($resource);

            // Assert
            expect($normalized)->toBeInstanceOf(ResourceObjectData::class);
            expect($normalized->relationships)->toHaveKey('user');
            expect($normalized->relationships['user'])->not->toBeArray();
        });

        test('normalizes resource without relationships', function (): void {
            // Arrange
            $user = User::query()->create([
                'name' => 'John Doe',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Act
            $resource = ResourceRepository::get($user);
            $normalized = ResourceNormalizer::normalize($resource);

            // Assert
            expect($normalized)->toBeInstanceOf(ResourceObjectData::class);
            expect($normalized->relationships)->toBeEmpty();
        });
    });

    describe('Sad Paths', function (): void {
        test('handles resource with null relationship gracefully', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Lonely User', 'created_at' => now(), 'updated_at' => now()]);
            $user->load('posts'); // Loads but returns empty collection

            // Act
            $resource = ResourceRepository::get($user);
            $normalized = ResourceNormalizer::normalize($resource);

            // Assert - Should handle gracefully without throwing exceptions
            expect($normalized)->toBeInstanceOf(ResourceObjectData::class);
            expect($normalized->relationships)->toBeEmpty();
        });

        test('normalizes resource when relationship data is empty array', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'No Posts User', 'created_at' => now(), 'updated_at' => now()]);
            // Explicitly load posts which will be an empty collection
            $user->load('posts');

            // Act
            $resource = ResourceRepository::get($user);
            $normalized = ResourceNormalizer::normalize($resource);

            // Assert
            expect($normalized)->toBeInstanceOf(ResourceObjectData::class);
            // Empty relationships should result in empty relationships array
            expect($normalized->relationships)->toBeEmpty();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty one-to-many relationships', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'John Doe', 'created_at' => now(), 'updated_at' => now()]);
            $user->load('posts');

            // Act
            $resource = ResourceRepository::get($user);
            $normalized = ResourceNormalizer::normalize($resource);

            // Assert
            expect($normalized)->toBeInstanceOf(ResourceObjectData::class);
            expect($normalized->relationships)->toBeEmpty();
        });

        test('handles multiple relationship types simultaneously', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'John Doe', 'created_at' => now(), 'updated_at' => now()]);
            $post1 = $user->posts()->create(['name' => 'Post 1', 'created_at' => now(), 'updated_at' => now()]);
            $post2 = $user->posts()->create(['name' => 'Post 2', 'created_at' => now(), 'updated_at' => now()]);

            $user->load('posts');

            // Act
            $resource = ResourceRepository::get($user);
            $normalized = ResourceNormalizer::normalize($resource);

            // Assert
            expect($normalized)->toBeInstanceOf(ResourceObjectData::class);
            expect($normalized->relationships)->toHaveKey('posts');
            expect($normalized->relationships['posts'])->toBeArray();
            expect($normalized->relationships['posts'])->toHaveCount(2);
        });

        test('properly wraps singular relationship in array for iteration', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Jane Doe', 'created_at' => now(), 'updated_at' => now()]);
            $post = $user->posts()->create(['name' => 'Test Post', 'created_at' => now(), 'updated_at' => now()]);

            $post->load('user');

            // Act
            $resource = ResourceRepository::get($post);
            $normalized = ResourceNormalizer::normalize($resource);

            // Assert
            expect($normalized)->toBeInstanceOf(ResourceObjectData::class);
            expect($normalized->relationships)->toHaveKey('user');
            // One-to-one relationship should not be an array in the final output
            expect($normalized->relationships['user'])->not->toBeArray();
        });

        test('detects relationship cardinality by pluralization', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'John Doe', 'created_at' => now(), 'updated_at' => now()]);
            $post1 = $user->posts()->create(['name' => 'Post 1', 'created_at' => now(), 'updated_at' => now()]);
            $post2 = $user->posts()->create(['name' => 'Post 2', 'created_at' => now(), 'updated_at' => now()]);

            $user->load('posts');

            // Act
            $resource = ResourceRepository::get($user);
            $normalized = ResourceNormalizer::normalize($resource);

            // Assert - 'posts' is plural, so it should be an array
            expect($normalized->relationships)->toHaveKey('posts');
            expect($normalized->relationships['posts'])->toBeArray();
        });

        test('handles singular relationship name correctly with arr wrap', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Author', 'created_at' => now(), 'updated_at' => now()]);
            $post = $user->posts()->create(['name' => 'Article', 'created_at' => now(), 'updated_at' => now()]);
            $post->load('user');

            // Act
            $resource = ResourceRepository::get($post);
            $normalized = ResourceNormalizer::normalize($resource);

            // Assert - 'user' is singular (Str::plural('user') === 'users' !== 'user')
            // Line 50 wraps singular relationships with Arr::wrap for iteration
            expect($normalized->relationships)->toHaveKey('user');
            expect($normalized->relationships['user'])->not->toBeArray();
            expect($normalized->relationships['user'])->toBeInstanceOf(User::class);
        });

        test('differentiates between singular and plural relationship names', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Test User', 'created_at' => now(), 'updated_at' => now()]);
            $post1 = $user->posts()->create(['name' => 'Post 1', 'created_at' => now(), 'updated_at' => now()]);
            $post2 = $user->posts()->create(['name' => 'Post 2', 'created_at' => now(), 'updated_at' => now()]);
            $user->load('posts');

            // Act
            $resource = ResourceRepository::get($user);
            $normalized = ResourceNormalizer::normalize($resource);

            // Assert - 'posts' is already plural (Str::plural('posts') === 'posts')
            // Line 47 detects this and does NOT wrap in Arr::wrap
            expect($normalized->relationships)->toHaveKey('posts');
            expect($normalized->relationships['posts'])->toBeArray();
            expect($normalized->relationships['posts'])->toHaveCount(2);

            // Each item should be a Post model (line 58 adds them to array)
            foreach ($normalized->relationships['posts'] as $postModel) {
                expect($postModel)->toBeInstanceOf(Post::class);
            }
        });

        test('processes relationships with multiple iterations through foreach loop', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Prolific Author', 'created_at' => now(), 'updated_at' => now()]);
            $post1 = $user->posts()->create(['name' => 'First Post', 'created_at' => now(), 'updated_at' => now()]);
            $post2 = $user->posts()->create(['name' => 'Second Post', 'created_at' => now(), 'updated_at' => now()]);
            $post3 = $user->posts()->create(['name' => 'Third Post', 'created_at' => now(), 'updated_at' => now()]);
            $user->load('posts');

            // Act
            $resource = ResourceRepository::get($user);
            $normalized = ResourceNormalizer::normalize($resource);

            // Assert
            expect($normalized->relationships)->toHaveKey('posts');
            expect($normalized->relationships['posts'])->toBeArray();
            expect($normalized->relationships['posts'])->toHaveCount(3);

            // Verify each iteration added to the array correctly (line 58)
            expect($normalized->relationships['posts'][0])->toBeInstanceOf(Post::class);
            expect($normalized->relationships['posts'][1])->toBeInstanceOf(Post::class);
            expect($normalized->relationships['posts'][2])->toBeInstanceOf(Post::class);
        });

        test('correctly assigns one-to-one relationship without array wrapping in output', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Single Author', 'created_at' => now(), 'updated_at' => now()]);
            $post = $user->posts()->create(['name' => 'Unique Post', 'created_at' => now(), 'updated_at' => now()]);
            $post->load('user');

            // Act
            $resource = ResourceRepository::get($post);
            $normalized = ResourceNormalizer::normalize($resource);

            // Assert - Line 56: $pendingResourceObject['relationships'][$relationName] = $relationship;
            // One-to-one relationships are assigned directly (not as array element)
            expect($normalized->relationships)->toHaveKey('user');
            expect($normalized->relationships['user'])->toBeInstanceOf(User::class);
            expect($normalized->relationships['user'])->not->toBeArray();
        });
    });
});

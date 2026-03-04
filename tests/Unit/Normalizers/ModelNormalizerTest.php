<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\Forrst\Data\ResourceObjectData;
use Cline\Forrst\Normalizers\ModelNormalizer;
use Cline\Forrst\Repositories\ResourceRepository;
use Tests\Support\Models\Post;
use Tests\Support\Models\User;
use Tests\Support\Resources\PostResource;
use Tests\Support\Resources\UserResource;

describe('ModelNormalizer', function (): void {
    beforeEach(function (): void {
        ResourceRepository::register(Post::class, PostResource::class);
        ResourceRepository::register(User::class, UserResource::class);
    });

    describe('Happy Paths', function (): void {
        test('transforms a model to a document data structure', function (): void {
            $user = User::query()->create([
                'name' => 'John',
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            Post::query()->create([
                'user_id' => $user->id,
                'name' => 'John',
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            $document = ModelNormalizer::normalize($user->load('posts'));

            expect($document)->toBeInstanceOf(ResourceObjectData::class);
            expect($document)->toMatchSnapshot();
        });

        test('transforms a model with one-to-one relationship', function (): void {
            $user = User::query()->create([
                'name' => 'John',
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            $post = Post::query()->create([
                'user_id' => $user->id,
                'name' => 'Test Post',
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            $document = ModelNormalizer::normalize($post->load('user'));

            expect($document)->toBeInstanceOf(ResourceObjectData::class);
            expect($document->relationships)->toHaveKey('user');
        });

        test('transforms a model without relationships', function (): void {
            $user = User::query()->create([
                'name' => 'Jane',
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            $document = ModelNormalizer::normalize($user);

            expect($document)->toBeInstanceOf(ResourceObjectData::class);
            expect($document->type)->toBe('user');
        });

        test('transforms a model with multiple items in one-to-many relationship', function (): void {
            $user = User::query()->create([
                'name' => 'John',
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            Post::query()->create([
                'user_id' => $user->id,
                'name' => 'First Post',
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            Post::query()->create([
                'user_id' => $user->id,
                'name' => 'Second Post',
                'created_at' => CarbonImmutable::parse('02.01.2024'),
                'updated_at' => CarbonImmutable::parse('02.01.2024'),
            ]);

            Post::query()->create([
                'user_id' => $user->id,
                'name' => 'Third Post',
                'created_at' => CarbonImmutable::parse('03.01.2024'),
                'updated_at' => CarbonImmutable::parse('03.01.2024'),
            ]);

            $document = ModelNormalizer::normalize($user->load('posts'));

            expect($document)->toBeInstanceOf(ResourceObjectData::class);
            expect($document->relationships)->toHaveKey('posts');
            expect($document->relationships['posts'])->toHaveKey('data');
            expect($document->relationships['posts']['data'])->toBeArray();
            expect($document->relationships['posts']['data'])->toHaveCount(3);
        });

        test('transforms a model with nested relationships', function (): void {
            $user = User::query()->create([
                'name' => 'John',
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            $post = Post::query()->create([
                'user_id' => $user->id,
                'name' => 'Post with nested user',
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            // Test nested relationships by loading posts with their user
            $document = ModelNormalizer::normalize($user->load('posts.user'));

            expect($document)->toBeInstanceOf(ResourceObjectData::class);
            expect($document->relationships)->toHaveKey('posts');
            expect($document->relationships['posts'])->toHaveKey('data');
            expect($document->relationships['posts']['data'])->toBeArray();
            // JSON:API resource identifier has type and id
            expect($document->relationships['posts']['data'][0])->toHaveKey('type');
            expect($document->relationships['posts']['data'][0]['type'])->toBe('post');
        });

        test('transforms a model with mixed cardinality relationships', function (): void {
            $user = User::query()->create([
                'name' => 'John',
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            $post = Post::query()->create([
                'user_id' => $user->id,
                'name' => 'Post with both relationship types',
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            $anotherPost = Post::query()->create([
                'user_id' => $user->id,
                'name' => 'Another Post',
                'created_at' => CarbonImmutable::parse('02.01.2024'),
                'updated_at' => CarbonImmutable::parse('02.01.2024'),
            ]);

            // Test model with one-to-one relationship (Post->User)
            // JSON:API format: relationships contain { data: { type, id } }
            $document = ModelNormalizer::normalize($post->load('user'));

            expect($document)->toBeInstanceOf(ResourceObjectData::class);
            expect($document->relationships)->toHaveKey('user');
            expect($document->relationships['user'])->toHaveKey('data');
            expect($document->relationships['user']['data'])->toBeArray();
            expect($document->relationships['user']['data']['type'])->toBe('user');
        });
    });

    describe('Edge Cases', function (): void {
        test('handles model with null relationship', function (): void {
            $user = User::query()->create([
                'name' => 'John',
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            $post = Post::query()->create([
                'user_id' => $user->id,
                'name' => 'Post with null relation',
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            // Manually set the relationship to null to test the null check
            $post->setRelation('user', null);

            $document = ModelNormalizer::normalize($post);

            expect($document)->toBeInstanceOf(ResourceObjectData::class);
            expect($document->type)->toBe('post');
            expect($document->relationships)->toBeNull();
        });

        test('handles empty relationship collection', function (): void {
            $user = User::query()->create([
                'name' => 'Jane',
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            // Load posts relationship but user has no posts
            $document = ModelNormalizer::normalize($user->load('posts'));

            expect($document)->toBeInstanceOf(ResourceObjectData::class);
            expect($document->type)->toBe('user');
            expect($document->relationships)->not->toHaveKey('posts');
        });

        test('handles model with only empty relationships loaded', function (): void {
            $user = User::query()->create([
                'name' => 'Bob',
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            $user->loadMissing('posts');

            $document = ModelNormalizer::normalize($user);

            expect($document)->toBeInstanceOf(ResourceObjectData::class);
            expect($document->relationships)->toBeNull();
        });
    });
});

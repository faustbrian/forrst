<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\Forrst\Data\CallData;
use Cline\Forrst\Data\DocumentData;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Repositories\ResourceRepository;
use Cline\Forrst\Transformers\Transformer;
use Illuminate\Support\Collection;
use Tests\Support\Models\Post;
use Tests\Support\Models\User;
use Tests\Support\Resources\PostResource;
use Tests\Support\Resources\UserResource;

describe('Transformer', function (): void {
    beforeEach(function (): void {
        ResourceRepository::register(Post::class, PostResource::class);
        ResourceRepository::register(User::class, UserResource::class);
    });

    describe('Happy Paths', function (): void {
        test('transforms a model to a document data structure', function (): void {
            $document = Transformer::create(
                RequestObjectData::from([
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => '1',
                    'call' => CallData::from([
                        'function' => 'get',
                    ]),
                ]),
            )->item(
                User::query()->create([
                    'name' => 'John',
                    'created_at' => CarbonImmutable::parse('01.01.2024'),
                    'updated_at' => CarbonImmutable::parse('01.01.2024'),
                ]),
            );

            expect($document)->toBeInstanceOf(DocumentData::class);
            expect($document)->toMatchSnapshot();
        });

        test('transforms a resource interface to a document data structure', function (): void {
            $user = User::query()->create([
                'name' => 'John',
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            $document = Transformer::create(
                RequestObjectData::from([
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => '1',
                    'call' => CallData::from([
                        'function' => 'get',
                    ]),
                ]),
            )->item(
                new UserResource($user),
            );

            expect($document)->toBeInstanceOf(DocumentData::class);
            expect($document->data)->toBeArray();
            expect($document->data)->toHaveKey('type');
            expect($document->data)->toHaveKey('id');
        });

        test('transforms a collection to a document data structure', function (): void {
            $document = Transformer::create(
                RequestObjectData::from([
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => '1',
                    'call' => CallData::from([
                        'function' => 'get',
                    ]),
                ]),
            )->collection(
                new Collection([
                    User::query()->create([
                        'name' => 'John',
                        'created_at' => CarbonImmutable::parse('01.01.2024'),
                        'updated_at' => CarbonImmutable::parse('01.01.2024'),
                    ]),
                    User::query()->create([
                        'name' => 'Jane',
                        'created_at' => CarbonImmutable::parse('01.01.2024'),
                        'updated_at' => CarbonImmutable::parse('01.01.2024'),
                    ]),
                ]),
            );

            expect($document)->toBeInstanceOf(DocumentData::class);
            expect($document)->toMatchSnapshot();
        });

        test('transforms a collection of resource interfaces to a document data structure', function (): void {
            $user1 = User::query()->create([
                'name' => 'John',
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            $user2 = User::query()->create([
                'name' => 'Jane',
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            $document = Transformer::create(
                RequestObjectData::from([
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => '1',
                    'call' => CallData::from([
                        'function' => 'get',
                    ]),
                ]),
            )->collection(
                new Collection([
                    new UserResource($user1),
                    new UserResource($user2),
                ]),
            );

            expect($document)->toBeInstanceOf(DocumentData::class);
            expect($document->data)->toBeArray();
            expect($document->data)->toHaveCount(2);
            expect($document->data[0])->toHaveKey('type');
            expect($document->data[0])->toHaveKey('id');
            expect($document->data[1])->toHaveKey('type');
            expect($document->data[1])->toHaveKey('id');
        });

        test('transforms cursor paginated results to a document data structure', function (): void {
            foreach (range(1, 101) as $index) {
                User::query()->create([
                    'name' => 'John '.$index,
                    'created_at' => CarbonImmutable::parse('01.01.2024'),
                    'updated_at' => CarbonImmutable::parse('01.01.2024'),
                ]);
            }

            $document = Transformer::create(
                RequestObjectData::from([
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => '1',
                    'call' => CallData::from([
                        'function' => 'get',
                    ]),
                ]),
            )->cursorPaginate(
                UserResource::query(
                    RequestObjectData::from([
                        'protocol' => ProtocolData::forrst()->toArray(),
                        'id' => '1',
                        'call' => CallData::from([
                            'function' => 'get',
                        ]),
                    ]),
                ),
            );

            expect($document)->toBeInstanceOf(DocumentData::class);
            expect($document)->toMatchSnapshot();
        });

        test('transforms length-aware paginated results to a document data structure', function (): void {
            foreach (range(1, 101) as $index) {
                User::query()->create([
                    'name' => 'John '.$index,
                    'created_at' => CarbonImmutable::parse('01.01.2024'),
                    'updated_at' => CarbonImmutable::parse('01.01.2024'),
                ]);
            }

            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => '1',
                'call' => CallData::from([
                    'function' => 'get',
                ]),
            ]);

            $document = Transformer::create($requestObject)->paginate(UserResource::query($requestObject));

            expect($document)->toBeInstanceOf(DocumentData::class);
            expect($document)->toMatchSnapshot();
        });

        test('transforms simply paginated results to a document data structure', function (): void {
            foreach (range(1, 101) as $index) {
                User::query()->create([
                    'name' => 'John '.$index,
                    'created_at' => CarbonImmutable::parse('01.01.2024'),
                    'updated_at' => CarbonImmutable::parse('01.01.2024'),
                ]);
            }

            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => '1',
                'call' => CallData::from([
                    'function' => 'get',
                ]),
            ]);

            $document = Transformer::create($requestObject)->simplePaginate(UserResource::query($requestObject));

            expect($document)->toBeInstanceOf(DocumentData::class);
            expect($document)->toMatchSnapshot();
        });
    });

    describe('Edge Cases', function (): void {
        test('paginate does not include metadata when all results fit on single page', function (): void {
            // Arrange - Create only 5 users (less than default page size of 100)
            foreach (range(1, 5) as $index) {
                User::query()->create([
                    'name' => 'User '.$index,
                    'created_at' => CarbonImmutable::parse('01.01.2024'),
                    'updated_at' => CarbonImmutable::parse('01.01.2024'),
                ]);
            }

            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => '1',
                'call' => CallData::from([
                    'function' => 'get',
                ]),
            ]);

            // Act
            $document = Transformer::create($requestObject)->paginate(UserResource::query($requestObject));

            // Assert - No meta key should be present when hasPages() is false
            expect($document)->toBeInstanceOf(DocumentData::class);
            expect($document->data)->toHaveCount(5);
            expect($document->toArray())->not()->toHaveKey('meta');
        });

        test('simple paginate does not include metadata when all results fit on single page', function (): void {
            // Arrange - Create only 5 users (less than default page size of 100)
            foreach (range(1, 5) as $index) {
                User::query()->create([
                    'name' => 'User '.$index,
                    'created_at' => CarbonImmutable::parse('01.01.2024'),
                    'updated_at' => CarbonImmutable::parse('01.01.2024'),
                ]);
            }

            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => '1',
                'call' => CallData::from([
                    'function' => 'get',
                ]),
            ]);

            // Act
            $document = Transformer::create($requestObject)->simplePaginate(UserResource::query($requestObject));

            // Assert - No meta key should be present when hasPages() is false
            expect($document)->toBeInstanceOf(DocumentData::class);
            expect($document->data)->toHaveCount(5);
            expect($document->toArray())->not()->toHaveKey('meta');
        });

        test('cursor paginate does not include metadata when all results fit on single page', function (): void {
            // Arrange - Create only 5 users (less than default page size of 100)
            foreach (range(1, 5) as $index) {
                User::query()->create([
                    'name' => 'User '.$index,
                    'created_at' => CarbonImmutable::parse('01.01.2024'),
                    'updated_at' => CarbonImmutable::parse('01.01.2024'),
                ]);
            }

            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => '1',
                'call' => CallData::from([
                    'function' => 'get',
                ]),
            ]);

            // Act
            $document = Transformer::create($requestObject)->cursorPaginate(UserResource::query($requestObject));

            // Assert - No meta key should be present when hasPages() is false
            expect($document)->toBeInstanceOf(DocumentData::class);
            expect($document->data)->toHaveCount(5);
            expect($document->toArray())->not()->toHaveKey('meta');
        });

        test('cursor paginate uses custom page size from request parameters', function (): void {
            // Arrange - Create 25 users
            foreach (range(1, 25) as $index) {
                User::query()->create([
                    'name' => 'User '.$index,
                    'created_at' => CarbonImmutable::parse('01.01.2024'),
                    'updated_at' => CarbonImmutable::parse('01.01.2024'),
                ]);
            }

            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => '1',
                'call' => CallData::from([
                    'function' => 'get',
                    'arguments' => [
                        'page' => [
                            'size' => 10,
                        ],
                    ],
                ]),
            ]);

            // Act
            $document = Transformer::create($requestObject)->cursorPaginate(UserResource::query($requestObject));

            // Assert - Should only return 10 items per the custom page size
            expect($document)->toBeInstanceOf(DocumentData::class);
            expect($document->data)->toHaveCount(10);
            expect($document->toArray())->toHaveKey('meta');
            expect($document->meta)->toHaveKey('page');
        });

        test('cursor paginate uses cursor from request parameters for navigation', function (): void {
            // Arrange - Create 25 users
            foreach (range(1, 25) as $index) {
                User::query()->create([
                    'name' => 'User '.$index,
                    'created_at' => CarbonImmutable::parse('01.01.2024'),
                    'updated_at' => CarbonImmutable::parse('01.01.2024'),
                ]);
            }

            // First, get the initial page to extract the next cursor
            $initialRequest = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => '1',
                'call' => CallData::from([
                    'function' => 'get',
                    'arguments' => [
                        'page' => [
                            'size' => 10,
                        ],
                    ],
                ]),
            ]);

            $initialDocument = Transformer::create($initialRequest)->cursorPaginate(UserResource::query($initialRequest));
            $nextCursor = $initialDocument->meta['page']['cursor']['next'];

            // Act - Use the next cursor to get the second page
            $requestWithCursor = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => '1',
                'call' => CallData::from([
                    'function' => 'get',
                    'arguments' => [
                        'page' => [
                            'size' => 10,
                            'cursor' => $nextCursor,
                        ],
                    ],
                ]),
            ]);

            $document = Transformer::create($requestWithCursor)->cursorPaginate(UserResource::query($requestWithCursor));

            // Assert - Should return the next 10 items
            expect($document)->toBeInstanceOf(DocumentData::class);
            expect($document->data)->toHaveCount(10);
            expect($document->toArray())->toHaveKey('meta');
            expect($document->meta['page']['cursor']['prev'])->not()->toBeNull();
        });

        test('paginate uses custom page size from request parameters', function (): void {
            // Arrange - Create 25 users
            foreach (range(1, 25) as $index) {
                User::query()->create([
                    'name' => 'User '.$index,
                    'created_at' => CarbonImmutable::parse('01.01.2024'),
                    'updated_at' => CarbonImmutable::parse('01.01.2024'),
                ]);
            }

            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => '1',
                'call' => CallData::from([
                    'function' => 'get',
                    'arguments' => [
                        'page' => [
                            'size' => 10,
                        ],
                    ],
                ]),
            ]);

            // Act
            $document = Transformer::create($requestObject)->paginate(UserResource::query($requestObject));

            // Assert - Should only return 10 items per the custom page size
            expect($document)->toBeInstanceOf(DocumentData::class);
            expect($document->data)->toHaveCount(10);
            expect($document->toArray())->toHaveKey('meta');
        });

        test('paginate uses page number from request parameters', function (): void {
            // Arrange - Create 25 users
            foreach (range(1, 25) as $index) {
                User::query()->create([
                    'name' => 'User '.$index,
                    'created_at' => CarbonImmutable::parse('01.01.2024'),
                    'updated_at' => CarbonImmutable::parse('01.01.2024'),
                ]);
            }

            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => '1',
                'call' => CallData::from([
                    'function' => 'get',
                    'arguments' => [
                        'page' => [
                            'size' => 10,
                            'number' => 2,
                        ],
                    ],
                ]),
            ]);

            // Act
            $document = Transformer::create($requestObject)->paginate(UserResource::query($requestObject));

            // Assert - Should return page 2
            expect($document)->toBeInstanceOf(DocumentData::class);
            expect($document->data)->toHaveCount(10);
            expect($document->meta['page']['number']['self'])->toBe(2);
            expect($document->meta['page']['number']['prev'])->toBe(1);
            expect($document->meta['page']['number']['next'])->toBe(3);
        });

        test('simple paginate uses custom page size from request parameters', function (): void {
            // Arrange - Create 25 users
            foreach (range(1, 25) as $index) {
                User::query()->create([
                    'name' => 'User '.$index,
                    'created_at' => CarbonImmutable::parse('01.01.2024'),
                    'updated_at' => CarbonImmutable::parse('01.01.2024'),
                ]);
            }

            $requestObject = RequestObjectData::from([
                'protocol' => ProtocolData::forrst()->toArray(),
                'id' => '1',
                'call' => CallData::from([
                    'function' => 'get',
                    'arguments' => [
                        'page' => [
                            'size' => 10,
                        ],
                    ],
                ]),
            ]);

            // Act
            $document = Transformer::create($requestObject)->simplePaginate(UserResource::query($requestObject));

            // Assert - Should only return 10 items per the custom page size
            expect($document)->toBeInstanceOf(DocumentData::class);
            expect($document->data)->toHaveCount(10);
            expect($document->toArray())->toHaveKey('meta');
        });

        test('collection handles empty collection', function (): void {
            // Arrange
            $emptyCollection = new Collection([]);

            // Act
            $document = Transformer::create(
                RequestObjectData::from([
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => '1',
                    'call' => CallData::from([
                        'function' => 'get',
                    ]),
                ]),
            )->collection($emptyCollection);

            // Assert
            expect($document)->toBeInstanceOf(DocumentData::class);
            expect($document->data)->toBeArray();
            expect($document->data)->toHaveCount(0);
        });

        test('collection handles mixed model and resource types', function (): void {
            // Arrange
            $user = User::query()->create([
                'name' => 'John',
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            $post = Post::query()->create([
                'name' => 'Test Post',
                'user_id' => $user->id,
                'created_at' => CarbonImmutable::parse('01.01.2024'),
                'updated_at' => CarbonImmutable::parse('01.01.2024'),
            ]);

            $collection = new Collection([
                $user,
                new PostResource($post),
            ]);

            // Act
            $document = Transformer::create(
                RequestObjectData::from([
                    'protocol' => ProtocolData::forrst()->toArray(),
                    'id' => '1',
                    'call' => CallData::from([
                        'function' => 'get',
                    ]),
                ]),
            )->collection($collection);

            // Assert
            expect($document)->toBeInstanceOf(DocumentData::class);
            expect($document->data)->toBeArray();
            expect($document->data)->toHaveCount(2);
            expect($document->data[0])->toHaveKey('type');
            expect($document->data[1])->toHaveKey('type');
        });
    });
});

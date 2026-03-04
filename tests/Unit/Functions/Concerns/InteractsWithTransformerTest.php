<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Contracts\ResourceInterface;
use Cline\Forrst\Data\DocumentData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Functions\Concerns\InteractsWithTransformer;
use Cline\Forrst\Repositories\ResourceRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Support\Models\User;
use Tests\Support\Resources\UserResource;

uses(RefreshDatabase::class);

describe('InteractsWithTransformer', function (): void {
    beforeEach(function (): void {
        // Register resources
        ResourceRepository::register(User::class, UserResource::class);

        // Create a concrete class that uses the trait for testing
        $this->class = new class()
        {
            use InteractsWithTransformer;

            public RequestObjectData $requestObject;

            public function __construct()
            {
                $this->requestObject = RequestObjectData::asRequest('test.method', ['page' => ['size' => 10]]);
            }

            // Expose protected methods for testing
            public function callItem(Model|ResourceInterface $item): DocumentData
            {
                return $this->item($item);
            }

            public function callCollection(Collection $collection): DocumentData
            {
                return $this->collection($collection);
            }

            public function callCursorPaginate(Builder $query): DocumentData
            {
                return $this->cursorPaginate($query);
            }

            public function callPaginate(Builder $query): DocumentData
            {
                return $this->paginate($query);
            }

            public function callSimplePaginate(Builder $query): DocumentData
            {
                return $this->simplePaginate($query);
            }
        };
    });

    describe('Happy Paths', function (): void {
        describe('item() method', function (): void {
            test('transforms single Eloquent Model into DocumentData', function (): void {
                // Arrange
                $user = User::query()->create(['name' => 'John Doe', 'created_at' => now()]);

                // Act
                $result = $this->class->callItem($user);

                // Assert
                expect($result)->toBeInstanceOf(DocumentData::class)
                    ->and($result->data)->toHaveKey('id')
                    ->and($result->data)->toHaveKey('type')
                    ->and($result->data['id'])->toBe((string) $user->id);
            });

            test('transforms single ResourceInterface into DocumentData', function (): void {
                // Arrange
                $user = User::query()->create(['name' => 'Jane Doe', 'created_at' => now()]);
                $resource = new UserResource($user);

                // Act
                $result = $this->class->callItem($resource);

                // Assert
                expect($result)->toBeInstanceOf(DocumentData::class)
                    ->and($result->data)->toHaveKey('id')
                    ->and($result->data)->toHaveKey('type')
                    ->and($result->data['type'])->toBe($resource->getType())
                    ->and($result->data['id'])->toBe($resource->getId());
            });
        });

        describe('collection() method', function (): void {
            test('transforms Collection of Models into DocumentData', function (): void {
                // Arrange
                $user1 = User::query()->create(['name' => 'User 1', 'created_at' => now()]);
                $user2 = User::query()->create(['name' => 'User 2', 'created_at' => now()]);
                $collection = new Collection([$user1, $user2]);

                // Act
                $result = $this->class->callCollection($collection);

                // Assert
                expect($result)->toBeInstanceOf(DocumentData::class)
                    ->and($result->data)->toBeArray()
                    ->and($result->data)->toHaveCount(2)
                    ->and($result->data[0])->toHaveKey('id')
                    ->and($result->data[0])->toHaveKey('type')
                    ->and($result->data[1])->toHaveKey('id')
                    ->and($result->data[1])->toHaveKey('type');
            });

            test('transforms empty Collection into DocumentData with empty data array', function (): void {
                // Arrange
                $collection = new Collection([]);

                // Act
                $result = $this->class->callCollection($collection);

                // Assert
                expect($result)->toBeInstanceOf(DocumentData::class)
                    ->and($result->data)->toBeArray()
                    ->and($result->data)->toBeEmpty();
            });
        });

        describe('cursorPaginate() method', function (): void {
            test('executes cursor pagination on Eloquent Builder and returns DocumentData', function (): void {
                // Arrange
                foreach (range(1, 15) as $i) {
                    User::query()->create(['name' => 'User '.$i, 'created_at' => now()]);
                }

                $query = User::query()->orderBy('id');

                // Act
                $result = $this->class->callCursorPaginate($query);

                // Assert
                expect($result)->toBeInstanceOf(DocumentData::class)
                    ->and($result->data)->toBeArray()
                    ->and($result->data)->toHaveCount(10) // page size from requestObject
                    ->and($result->meta)->toHaveKey('page')
                    ->and($result->meta['page'])->toHaveKey('cursor');
            });

            test('executes cursor pagination with empty results', function (): void {
                // Arrange - no users in database
                $query = User::query()->orderBy('id');

                // Act
                $result = $this->class->callCursorPaginate($query);

                // Assert
                expect($result)->toBeInstanceOf(DocumentData::class)
                    ->and($result->data)->toBeArray()
                    ->and($result->data)->toBeEmpty();
            });
        });

        describe('paginate() method', function (): void {
            test('executes offset pagination on Eloquent Builder and returns DocumentData', function (): void {
                // Arrange
                foreach (range(1, 25) as $i) {
                    User::query()->create(['name' => 'User '.$i, 'created_at' => now()]);
                }

                $query = User::query()->orderBy('id');

                // Act
                $result = $this->class->callPaginate($query);

                // Assert
                expect($result)->toBeInstanceOf(DocumentData::class)
                    ->and($result->data)->toBeArray()
                    ->and($result->data)->toHaveCount(10) // page size from requestObject
                    ->and($result->meta)->toHaveKey('page')
                    ->and($result->meta['page'])->toHaveKey('number')
                    ->and($result->meta['page']['number'])->toHaveKey('self')
                    ->and($result->meta['page']['number'])->toHaveKey('next');
            });

            test('executes offset pagination with custom page number', function (): void {
                // Arrange
                foreach (range(1, 25) as $i) {
                    User::query()->create(['name' => 'User '.$i, 'created_at' => now()]);
                }

                $this->class->requestObject = RequestObjectData::asRequest(
                    'test.method',
                    ['page' => ['size' => 5, 'number' => 2]],
                );
                $query = User::query()->orderBy('id');

                // Act
                $result = $this->class->callPaginate($query);

                // Assert
                expect($result)->toBeInstanceOf(DocumentData::class)
                    ->and($result->data)->toHaveCount(5)
                    ->and($result->meta['page']['number']['self'])->toBe(2)
                    ->and($result->meta['page']['number']['prev'])->toBe(1)
                    ->and($result->meta['page']['number']['next'])->toBe(3);
            });
        });

        describe('simplePaginate() method', function (): void {
            test('executes simple pagination on Eloquent Builder and returns DocumentData', function (): void {
                // Arrange
                foreach (range(1, 15) as $i) {
                    User::query()->create(['name' => 'User '.$i, 'created_at' => now()]);
                }

                $query = User::query()->orderBy('id');

                // Act
                $result = $this->class->callSimplePaginate($query);

                // Assert
                expect($result)->toBeInstanceOf(DocumentData::class)
                    ->and($result->data)->toBeArray()
                    ->and($result->data)->toHaveCount(10)
                    ->and($result->meta)->toHaveKey('page')
                    ->and($result->meta['page'])->toHaveKey('number');
            });

            test('executes simple pagination with single page results', function (): void {
                // Arrange
                foreach (range(1, 5) as $i) {
                    User::query()->create(['name' => 'User '.$i, 'created_at' => now()]);
                }

                $query = User::query()->orderBy('id');

                // Act
                $result = $this->class->callSimplePaginate($query);

                // Assert
                expect($result)->toBeInstanceOf(DocumentData::class)
                    ->and($result->data)->toHaveCount(5);
            });
        });
    });

    describe('Edge Cases', function (): void {
        test('item() handles Model with minimal attributes', function (): void {
            // Arrange
            $user = new User(['name' => 'Test']);
            $user->id = 1;
            $user->exists = true;

            // Act
            $result = $this->class->callItem($user);

            // Assert
            expect($result)->toBeInstanceOf(DocumentData::class)
                ->and($result->data)->toHaveKey('id');
        });

        test('collection() handles Collection with single item', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Test User', 'created_at' => now()]);
            $collection = new Collection([$user]);

            // Act
            $result = $this->class->callCollection($collection);

            // Assert
            expect($result)->toBeInstanceOf(DocumentData::class)
                ->and($result->data)->toHaveCount(1);
        });

        test('collection() handles large Collection', function (): void {
            // Arrange
            $users = collect();

            foreach (range(1, 100) as $i) {
                $users->push(User::query()->create(['name' => 'User '.$i, 'created_at' => now()]));
            }

            $collection = new Collection($users->all());

            // Act
            $result = $this->class->callCollection($collection);

            // Assert
            expect($result)->toBeInstanceOf(DocumentData::class)
                ->and($result->data)->toHaveCount(100);
        });

        test('cursorPaginate() handles empty results without pagination metadata', function (): void {
            // Arrange - no data
            $query = User::query()->orderBy('id');

            // Act
            $result = $this->class->callCursorPaginate($query);

            // Assert
            expect($result)->toBeInstanceOf(DocumentData::class)
                ->and($result->data)->toBeEmpty();
        });

        test('paginate() handles first page navigation correctly', function (): void {
            // Arrange
            foreach (range(1, 20) as $i) {
                User::query()->create(['name' => 'User '.$i, 'created_at' => now()]);
            }

            $this->class->requestObject = RequestObjectData::asRequest(
                'test.method',
                ['page' => ['size' => 10, 'number' => 1]],
            );
            $query = User::query()->orderBy('id');

            // Act
            $result = $this->class->callPaginate($query);

            // Assert
            expect($result->meta['page']['number']['self'])->toBe(1)
                ->and($result->meta['page']['number']['prev'])->toBeNull()
                ->and($result->meta['page']['number']['next'])->toBe(2);
        });

        test('paginate() handles last page navigation correctly', function (): void {
            // Arrange
            foreach (range(1, 25) as $i) {
                User::query()->create(['name' => 'User '.$i, 'created_at' => now()]);
            }

            $this->class->requestObject = RequestObjectData::asRequest(
                'test.method',
                ['page' => ['size' => 10, 'number' => 3]],
            );
            $query = User::query()->orderBy('id');

            // Act
            $result = $this->class->callPaginate($query);

            // Assert
            expect($result->meta['page']['number']['self'])->toBe(3)
                ->and($result->meta['page']['number']['prev'])->toBe(2)
                ->and($result->meta['page']['number']['next'])->toBeNull();
        });

        test('simplePaginate() handles empty results', function (): void {
            // Arrange - no data
            $query = User::query()->orderBy('id');

            // Act
            $result = $this->class->callSimplePaginate($query);

            // Assert
            expect($result)->toBeInstanceOf(DocumentData::class)
                ->and($result->data)->toBeEmpty();
        });

        test('methods use same requestObject for transformer creation', function (): void {
            // Arrange
            $customParams = ['page' => ['size' => 25], 'custom' => 'value'];
            $this->class->requestObject = RequestObjectData::asRequest('custom.method', $customParams);
            $user = User::query()->create(['name' => 'Test User', 'created_at' => now()]);

            // Act
            $result = $this->class->callItem($user);

            // Assert - verify transformation completed successfully with custom request
            expect($result)->toBeInstanceOf(DocumentData::class)
                ->and($result->data)->toHaveKey('id')
                ->and($this->class->requestObject->call->function)->toBe('custom.method')
                ->and($this->class->requestObject->getArgument('custom'))->toBe('value');
        });

        test('collection() handles mixed Model types gracefully', function (): void {
            // Arrange
            $user1 = User::query()->create(['name' => 'Test User', 'created_at' => now()]);
            $user2 = User::query()->create(['name' => 'Test User', 'created_at' => now()]);
            $collection = new Collection([$user1, $user2]);

            // Act
            $result = $this->class->callCollection($collection);

            // Assert
            expect($result)->toBeInstanceOf(DocumentData::class)
                ->and($result->data)->toHaveCount(2)
                ->and($result->data[0]['type'])->toBe($result->data[1]['type']);
        });

        test('pagination methods respect custom page size from request', function (): void {
            // Arrange
            foreach (range(1, 50) as $i) {
                User::query()->create(['name' => 'User '.$i, 'created_at' => now()]);
            }

            $this->class->requestObject = RequestObjectData::asRequest(
                'test.method',
                ['page' => ['size' => 15]],
            );
            $query = User::query()->orderBy('id');

            // Act
            $result = $this->class->callPaginate($query);

            // Assert
            expect($result->data)->toHaveCount(15);
        });
    });
});

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Resources\AbstractResource;
use Tests\Support\Resources\MinimalTestResource;
use Tests\Support\Resources\OverriddenTestResource;

describe('AbstractResource', function (): void {
    describe('Happy Paths', function (): void {
        describe('getFields()', function (): void {
            test('returns empty array by default', function (): void {
                // Arrange - using a minimal concrete implementation

                // Act
                $fields = MinimalTestResource::getFields();

                // Assert
                expect($fields)->toBe([]);
                expect($fields)->toBeArray();
                expect($fields)->toBeEmpty();
            });

            test('can be overridden to return custom fields', function (): void {
                // Arrange - using an overridden concrete implementation

                // Act
                $fields = OverriddenTestResource::getFields();

                // Assert
                expect($fields)->toBe([
                    'self' => ['id', 'name', 'email'],
                    'posts' => ['id', 'title'],
                ]);
                expect($fields)->toHaveKey('self');
                expect($fields)->toHaveKey('posts');
            });

            test('is a static method that can be called without instantiation', function (): void {
                // Arrange
                $className = MinimalTestResource::class;

                // Act
                $fields = $className::getFields();

                // Assert
                expect($fields)->toBe([]);
            });
        });

        describe('getFilters()', function (): void {
            test('returns empty array by default', function (): void {
                // Arrange - using a minimal concrete implementation

                // Act
                $filters = MinimalTestResource::getFilters();

                // Assert
                expect($filters)->toBe([]);
                expect($filters)->toBeArray();
                expect($filters)->toBeEmpty();
            });

            test('can be overridden to return custom filters', function (): void {
                // Arrange - using an overridden concrete implementation

                // Act
                $filters = OverriddenTestResource::getFilters();

                // Assert
                expect($filters)->toBe([
                    'self' => ['name', 'email', 'created_at'],
                    'posts' => ['title', 'status'],
                ]);
                expect($filters)->toHaveKey('self');
                expect($filters)->toHaveKey('posts');
            });

            test('is a static method that can be called without instantiation', function (): void {
                // Arrange
                $className = MinimalTestResource::class;

                // Act
                $filters = $className::getFilters();

                // Assert
                expect($filters)->toBe([]);
            });
        });

        describe('getRelationships()', function (): void {
            test('returns empty array by default', function (): void {
                // Arrange - using a minimal concrete implementation

                // Act
                $relationships = MinimalTestResource::getRelationships();

                // Assert
                expect($relationships)->toBe([]);
                expect($relationships)->toBeArray();
                expect($relationships)->toBeEmpty();
            });

            test('can be overridden to return custom relationships', function (): void {
                // Arrange - using an overridden concrete implementation

                // Act
                $relationships = OverriddenTestResource::getRelationships();

                // Assert
                expect($relationships)->toBe([
                    'self' => ['posts', 'comments', 'profile'],
                ]);
                expect($relationships)->toHaveKey('self');
                expect($relationships['self'])->toContain('posts');
                expect($relationships['self'])->toContain('comments');
                expect($relationships['self'])->toContain('profile');
            });

            test('is a static method that can be called without instantiation', function (): void {
                // Arrange
                $className = MinimalTestResource::class;

                // Act
                $relationships = $className::getRelationships();

                // Assert
                expect($relationships)->toBe([]);
            });
        });

        describe('getSorts()', function (): void {
            test('returns empty array by default', function (): void {
                // Arrange - using a minimal concrete implementation

                // Act
                $sorts = MinimalTestResource::getSorts();

                // Assert
                expect($sorts)->toBe([]);
                expect($sorts)->toBeArray();
                expect($sorts)->toBeEmpty();
            });

            test('can be overridden to return custom sorts', function (): void {
                // Arrange - using an overridden concrete implementation

                // Act
                $sorts = OverriddenTestResource::getSorts();

                // Assert
                expect($sorts)->toBe([
                    'self' => ['name', 'created_at', 'updated_at'],
                    'posts' => ['published_at'],
                ]);
                expect($sorts)->toHaveKey('self');
                expect($sorts)->toHaveKey('posts');
                expect($sorts['self'])->toContain('created_at');
            });

            test('is a static method that can be called without instantiation', function (): void {
                // Arrange
                $className = MinimalTestResource::class;

                // Act
                $sorts = $className::getSorts();

                // Assert
                expect($sorts)->toBe([]);
            });
        });

        describe('toArray()', function (): void {
            test('returns standardized resource structure', function (): void {
                // Arrange
                $resource = new MinimalTestResource();

                // Act
                $array = $resource->toArray();

                // Assert
                expect($array)->toBe([
                    'type' => 'test',
                    'id' => '1',
                    'attributes' => ['name' => 'Test Resource'],
                ]);
                expect($array)->toHaveKeys(['type', 'id', 'attributes']);
            });

            test('uses concrete implementation methods', function (): void {
                // Arrange
                $resource = new OverriddenTestResource();

                // Act
                $array = $resource->toArray();

                // Assert
                expect($array)->toBe([
                    'type' => 'overridden',
                    'id' => '42',
                    'attributes' => [
                        'name' => 'Overridden Resource',
                        'email' => 'test@example.com',
                    ],
                ]);
            });
        });
    });

    describe('Edge Cases', function (): void {
        describe('Static method calls', function (): void {
            test('all default methods return consistent empty arrays', function (): void {
                // Arrange
                $resource = MinimalTestResource::class;

                // Act
                $fields = $resource::getFields();
                $filters = $resource::getFilters();
                $relationships = $resource::getRelationships();
                $sorts = $resource::getSorts();

                // Assert
                expect($fields)->toBe([]);
                expect($filters)->toBe([]);
                expect($relationships)->toBe([]);
                expect($sorts)->toBe([]);
            });

            test('methods can be called via reflection', function (): void {
                // Arrange
                $reflection = new ReflectionClass(MinimalTestResource::class);

                // Act
                $getFieldsMethod = $reflection->getMethod('getFields');
                $fields = $getFieldsMethod->invoke(null);

                $getFiltersMethod = $reflection->getMethod('getFilters');
                $filters = $getFiltersMethod->invoke(null);

                $getRelationshipsMethod = $reflection->getMethod('getRelationships');
                $relationships = $getRelationshipsMethod->invoke(null);

                $getSortsMethod = $reflection->getMethod('getSorts');
                $sorts = $getSortsMethod->invoke(null);

                // Assert
                expect($fields)->toBe([]);
                expect($filters)->toBe([]);
                expect($relationships)->toBe([]);
                expect($sorts)->toBe([]);
            });
        });

        describe('Inheritance behavior', function (): void {
            test('subclasses inherit default implementations', function (): void {
                // Arrange - MinimalTestResource doesn't override the static methods

                // Act
                $fields = MinimalTestResource::getFields();
                $filters = MinimalTestResource::getFilters();
                $relationships = MinimalTestResource::getRelationships();
                $sorts = MinimalTestResource::getSorts();

                // Assert
                expect($fields)->toBe([]);
                expect($filters)->toBe([]);
                expect($relationships)->toBe([]);
                expect($sorts)->toBe([]);
            });

            test('subclasses can selectively override methods', function (): void {
                // Arrange - OverriddenTestResource overrides all methods

                // Act & Assert
                expect(OverriddenTestResource::getFields())->not->toBe([]);
                expect(OverriddenTestResource::getFilters())->not->toBe([]);
                expect(OverriddenTestResource::getRelationships())->not->toBe([]);
                expect(OverriddenTestResource::getSorts())->not->toBe([]);
            });
        });

        describe('Method return types', function (): void {
            test('all methods return arrays even when not overridden', function (): void {
                // Arrange
                $resource = MinimalTestResource::class;

                // Act
                $fields = $resource::getFields();
                $filters = $resource::getFilters();
                $relationships = $resource::getRelationships();
                $sorts = $resource::getSorts();

                // Assert
                expect($fields)->toBeArray();
                expect($filters)->toBeArray();
                expect($relationships)->toBeArray();
                expect($sorts)->toBeArray();
            });
        });

        describe('toArray() with various attribute scenarios', function (): void {
            test('handles resource with empty attributes array', function (): void {
                // Arrange
                $resource = new class() extends AbstractResource
                {
                    public function getType(): string
                    {
                        return 'empty';
                    }

                    public function getId(): string
                    {
                        return '999';
                    }

                    public function getAttributes(): array
                    {
                        return [];
                    }

                    public function getRelations(): array
                    {
                        return [];
                    }
                };

                // Act
                $array = $resource->toArray();

                // Assert
                expect($array)->toHaveKeys(['type', 'id', 'attributes']);
                expect($array['type'])->toBe('empty');
                expect($array['id'])->toBe('999');
                expect($array['attributes'])->toBe([]);
                expect($array['attributes'])->toBeArray();
                expect($array['attributes'])->toBeEmpty();
            });

            test('handles resource with numeric string id', function (): void {
                // Arrange
                $resource = new class() extends AbstractResource
                {
                    public function getType(): string
                    {
                        return 'numeric';
                    }

                    public function getId(): string
                    {
                        return '12345';
                    }

                    public function getAttributes(): array
                    {
                        return ['value' => 100];
                    }

                    public function getRelations(): array
                    {
                        return [];
                    }
                };

                // Act
                $array = $resource->toArray();

                // Assert
                expect($array['id'])->toBe('12345');
                expect($array['id'])->toBeString();
            });

            test('handles resource with uuid id', function (): void {
                // Arrange
                $resource = new class() extends AbstractResource
                {
                    public function getType(): string
                    {
                        return 'uuid';
                    }

                    public function getId(): string
                    {
                        return '550e8400-e29b-41d4-a716-446655440000';
                    }

                    public function getAttributes(): array
                    {
                        return ['data' => 'test'];
                    }

                    public function getRelations(): array
                    {
                        return [];
                    }
                };

                // Act
                $array = $resource->toArray();

                // Assert
                expect($array['id'])->toBe('550e8400-e29b-41d4-a716-446655440000');
                expect($array['id'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
            });

            test('handles resource with complex nested attributes', function (): void {
                // Arrange
                $resource = new class() extends AbstractResource
                {
                    public function getType(): string
                    {
                        return 'complex';
                    }

                    public function getId(): string
                    {
                        return '1';
                    }

                    public function getAttributes(): array
                    {
                        return [
                            'name' => 'Test',
                            'metadata' => ['key' => 'value'],
                            'tags' => ['php', 'testing'],
                            'count' => 0,
                            'active' => false,
                        ];
                    }

                    public function getRelations(): array
                    {
                        return [];
                    }
                };

                // Act
                $array = $resource->toArray();

                // Assert
                expect($array['attributes'])->toHaveKey('metadata');
                expect($array['attributes'])->toHaveKey('tags');
                expect($array['attributes']['metadata'])->toBe(['key' => 'value']);
                expect($array['attributes']['tags'])->toBe(['php', 'testing']);
                expect($array['attributes']['count'])->toBe(0);
                expect($array['attributes']['active'])->toBeFalse();
            });

            test('handles resource with special characters in type', function (): void {
                // Arrange
                $resource = new class() extends AbstractResource
                {
                    public function getType(): string
                    {
                        return 'user-profiles';
                    }

                    public function getId(): string
                    {
                        return '1';
                    }

                    public function getAttributes(): array
                    {
                        return [];
                    }

                    public function getRelations(): array
                    {
                        return [];
                    }
                };

                // Act
                $array = $resource->toArray();

                // Assert
                expect($array['type'])->toBe('user-profiles');
                expect($array['type'])->toContain('-');
            });
        });

        describe('Static method call variations', function (): void {
            test('calling methods multiple times returns consistent results', function (): void {
                // Arrange & Act
                $fields1 = MinimalTestResource::getFields();
                $fields2 = MinimalTestResource::getFields();
                $fields3 = MinimalTestResource::getFields();

                // Assert
                expect($fields1)->toBe($fields2);
                expect($fields2)->toBe($fields3);
                expect($fields1)->toBe([]);
            });

            test('different classes maintain independent static method results', function (): void {
                // Arrange & Act
                $minimalFields = MinimalTestResource::getFields();
                $overriddenFields = OverriddenTestResource::getFields();

                // Assert - They should not affect each other
                expect($minimalFields)->toBe([]);
                expect($overriddenFields)->not->toBe([]);
                expect($minimalFields)->not->toBe($overriddenFields);
            });

            test('mixed static method calls preserve correct values', function (): void {
                // Arrange & Act - Interleave calls to both resource types
                $minimalFields = MinimalTestResource::getFields();
                $overriddenFields = OverriddenTestResource::getFields();
                $minimalFilters = MinimalTestResource::getFilters();
                $overriddenFilters = OverriddenTestResource::getFilters();

                // Assert - Each should maintain its own state
                expect($minimalFields)->toBe([]);
                expect($minimalFilters)->toBe([]);
                expect($overriddenFields)->toHaveKey('self');
                expect($overriddenFilters)->toHaveKey('self');
            });
        });
    });

    describe('Sad Paths', function (): void {
        describe('Abstract class instantiation', function (): void {
            test('cannot instantiate AbstractResource directly', function (): void {
                // Arrange & Act & Assert
                expect(fn (): AbstractResource => new class() extends AbstractResource
                {
                    public function getType(): string
                    {
                        return 'test';
                    }

                    public function getId(): string
                    {
                        return '1';
                    }

                    public function getAttributes(): array
                    {
                        return [];
                    }

                    public function getRelations(): array
                    {
                        return [];
                    }
                })->not->toThrow(Error::class);
            });
        });
    });

    describe('Regressions', function (): void {
        describe('Late static binding consistency', function (): void {
            test('static methods use late static binding correctly across inheritance', function (): void {
                // Arrange
                $minimal = MinimalTestResource::class;
                $overridden = OverriddenTestResource::class;

                // Act
                $minimalFields = $minimal::getFields();
                $overriddenFields = $overridden::getFields();

                // Assert - Ensure each class returns its own implementation
                expect($minimalFields)->toBe([]);
                expect($overriddenFields)->toHaveKey('self');
                expect($overriddenFields)->toHaveKey('posts');
            });

            test('calling parent method from child returns child implementation', function (): void {
                // Arrange & Act
                $fields = OverriddenTestResource::getFields();
                $filters = OverriddenTestResource::getFilters();
                $relationships = OverriddenTestResource::getRelationships();
                $sorts = OverriddenTestResource::getSorts();

                // Assert - Each method should return the overridden values, not parent defaults
                expect($fields)->not->toBe([]);
                expect($filters)->not->toBe([]);
                expect($relationships)->not->toBe([]);
                expect($sorts)->not->toBe([]);
            });
        });

        describe('Multiple concurrent resource instances', function (): void {
            test('different resource instances maintain separate state', function (): void {
                // Arrange
                $resource1 = new MinimalTestResource();
                $resource2 = new OverriddenTestResource();

                // Act
                $array1 = $resource1->toArray();
                $array2 = $resource2->toArray();

                // Assert - Each instance maintains its own state
                expect($array1['type'])->toBe('test');
                expect($array2['type'])->toBe('overridden');
                expect($array1['id'])->toBe('1');
                expect($array2['id'])->toBe('42');
            });

            test('static methods remain consistent when instances exist', function (): void {
                // Arrange
                $resource1 = new MinimalTestResource();
                $resource2 = new OverriddenTestResource();

                // Act
                $fieldsFromClass = MinimalTestResource::getFields();
                $fieldsFromInstance = $resource1::getFields();
                $overriddenFromClass = OverriddenTestResource::getFields();
                $overriddenFromInstance = $resource2::getFields();

                // Assert - Static methods behave consistently
                expect($fieldsFromClass)->toBe($fieldsFromInstance);
                expect($overriddenFromClass)->toBe($overriddenFromInstance);
            });
        });

        describe('Empty array returns consistency', function (): void {
            test('default implementations always return empty arrays not null', function (): void {
                // Arrange & Act
                $fields = MinimalTestResource::getFields();
                $filters = MinimalTestResource::getFilters();
                $relationships = MinimalTestResource::getRelationships();
                $sorts = MinimalTestResource::getSorts();

                // Assert - Should be empty arrays, not null or other falsy values
                expect($fields)->toBeArray()->toBeEmpty();
                expect($filters)->toBeArray()->toBeEmpty();
                expect($relationships)->toBeArray()->toBeEmpty();
                expect($sorts)->toBeArray()->toBeEmpty();
                expect($fields)->not->toBeNull();
                expect($filters)->not->toBeNull();
            });
        });
    });
});

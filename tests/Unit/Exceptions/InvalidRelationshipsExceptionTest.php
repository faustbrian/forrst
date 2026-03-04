<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Exceptions\AbstractRequestException;
use Cline\Forrst\Exceptions\InvalidRelationshipsException;

describe('InvalidRelationshipsException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates exception with unknown and allowed relationships', function (): void {
            // Arrange
            $unknownRelationships = ['relationship1'];
            $allowedRelationships = ['relationship2', 'relationship3'];

            // Act
            $requestException = InvalidRelationshipsException::create($unknownRelationships, $allowedRelationships);

            // Assert
            expect($requestException)->toBeInstanceOf(AbstractRequestException::class);
            expect($requestException->toArray())->toMatchSnapshot();
            expect($requestException->getErrorCode())->toBe(ErrorCode::InvalidArguments->value);
            expect($requestException->getErrorMessage())->toBe('Invalid arguments');
        });

        test('creates exception with empty allowed relationships array', function (): void {
            // Arrange
            $unknownRelationships = ['posts', 'comments', 'tags'];
            $allowedRelationships = [];

            // Act
            $requestException = InvalidRelationshipsException::create($unknownRelationships, $allowedRelationships);

            // Assert
            expect($requestException)->toBeInstanceOf(InvalidRelationshipsException::class);
            expect($requestException->getErrorCode())->toBe(ErrorCode::InvalidArguments->value);
            expect($requestException->getErrorMessage())->toBe('Invalid arguments');

            $errorArray = $requestException->toArray();
            expect($errorArray['details'][0]['detail'])->toBe('Requested relationships `posts, comments, tags` are not allowed. There are no allowed relationships.');
            expect($errorArray['details'][0]['meta']['unknown'])->toBe('posts, comments, tags');
            expect($errorArray['details'][0]['meta']['allowed'])->toEqual([]);
        });

        test('creates exception with multiple unknown relationships', function (): void {
            // Arrange
            $unknownRelationships = ['author', 'editor', 'publisher'];
            $allowedRelationships = ['category', 'tags'];

            // Act
            $requestException = InvalidRelationshipsException::create($unknownRelationships, $allowedRelationships);

            // Assert
            expect($requestException)->toBeInstanceOf(InvalidRelationshipsException::class);
            expect($requestException->getErrorCode())->toBe(ErrorCode::InvalidArguments->value);

            $errorArray = $requestException->toArray();
            expect($errorArray['details'][0]['detail'])->toBe('Requested relationships `author, editor, publisher` are not allowed. Allowed relationships are `category, tags`.');
            expect($errorArray['details'][0]['status'])->toBe('422');
            expect($errorArray['details'][0]['source']['pointer'])->toBe('/params/relationships');
            expect($errorArray['details'][0]['title'])->toBe('Invalid relationships');
        });
    });

    describe('Sad Paths', function (): void {
        test('creates exception when some unknown relationships overlap with allowed', function (): void {
            // Arrange
            $unknownRelationships = ['posts', 'comments', 'author'];
            $allowedRelationships = ['author', 'tags'];

            // Act
            $requestException = InvalidRelationshipsException::create($unknownRelationships, $allowedRelationships);

            // Assert
            expect($requestException)->toBeInstanceOf(InvalidRelationshipsException::class);

            $errorArray = $requestException->toArray();
            // After array_diff, only truly unknown relationships remain
            expect($errorArray['details'][0]['detail'])->toBe('Requested relationships `posts, comments` are not allowed. Allowed relationships are `author, tags`.');
            expect($errorArray['details'][0]['meta']['unknown'])->toBe('posts, comments');
        });

        test('creates exception with empty unknown relationships after filtering', function (): void {
            // Arrange
            $unknownRelationships = ['author', 'tags']; // All are actually allowed
            $allowedRelationships = ['author', 'tags', 'category'];

            // Act
            $requestException = InvalidRelationshipsException::create($unknownRelationships, $allowedRelationships);

            // Assert
            expect($requestException)->toBeInstanceOf(InvalidRelationshipsException::class);

            $errorArray = $requestException->toArray();
            // After array_diff, no unknown relationships remain
            expect($errorArray['details'][0]['detail'])->toBe('Requested relationships `` are not allowed. Allowed relationships are `author, tags, category`.');
            expect($errorArray['details'][0]['meta']['unknown'])->toBe('');
        });
    });

    describe('Edge Cases', function (): void {
        test('creates exception with special characters in relationship names', function (): void {
            // Arrange
            $unknownRelationships = ['user-profile', 'meta_data', 'parent.child'];
            $allowedRelationships = [];

            // Act
            $requestException = InvalidRelationshipsException::create($unknownRelationships, $allowedRelationships);

            // Assert
            expect($requestException)->toBeInstanceOf(InvalidRelationshipsException::class);

            $errorArray = $requestException->toArray();
            expect($errorArray['details'][0]['detail'])->toBe('Requested relationships `user-profile, meta_data, parent.child` are not allowed. There are no allowed relationships.');
        });

        test('creates exception with very long relationship names', function (): void {
            // Arrange
            $longRelationshipName = str_repeat('a', 100);
            $unknownRelationships = [$longRelationshipName, 'normal'];
            $allowedRelationships = ['short'];

            // Act
            $requestException = InvalidRelationshipsException::create($unknownRelationships, $allowedRelationships);

            // Assert
            expect($requestException)->toBeInstanceOf(InvalidRelationshipsException::class);

            $errorArray = $requestException->toArray();
            expect($errorArray['details'][0]['detail'])->toContain($longRelationshipName);
            expect($errorArray['details'][0]['detail'])->toContain('Allowed relationships are `short`.');
        });

        test('creates exception with duplicate unknown relationships', function (): void {
            // Arrange
            $unknownRelationships = ['duplicate', 'duplicate', 'unique'];
            $allowedRelationships = [];

            // Act
            $requestException = InvalidRelationshipsException::create($unknownRelationships, $allowedRelationships);

            // Assert
            expect($requestException)->toBeInstanceOf(InvalidRelationshipsException::class);

            $errorArray = $requestException->toArray();
            // array_diff preserves duplicates, implode joins them
            expect($errorArray['details'][0]['detail'])->toBe('Requested relationships `duplicate, duplicate, unique` are not allowed. There are no allowed relationships.');
        });
    });
});

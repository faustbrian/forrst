<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Exceptions\AbstractRequestException;
use Cline\Forrst\Exceptions\ResourceNotFoundException;

describe('ResourceNotFoundException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates exception with default detail', function (): void {
            $requestException = ResourceNotFoundException::create();

            expect($requestException)->toBeInstanceOf(AbstractRequestException::class);
            expect($requestException->toArray())->toMatchSnapshot();
            expect($requestException->getErrorCode())->toBe(ErrorCode::InternalError->value);
            expect($requestException->getErrorMessage())->toBe('Server error');
            expect($requestException->getStatusCode())->toBe(404);
        });

        test('creates exception with custom detail', function (): void {
            $requestException = ResourceNotFoundException::create('User with ID 123 not found');

            expect($requestException)->toBeInstanceOf(AbstractRequestException::class);
            expect($requestException->toArray())->toMatchSnapshot();
            expect($requestException->getErrorCode())->toBe(ErrorCode::InternalError->value);
            expect($requestException->getErrorMessage())->toBe('Server error');
            expect($requestException->getStatusCode())->toBe(404);
        });
    });
});

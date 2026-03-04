<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Exceptions\AbstractRequestException;
use Cline\Forrst\Exceptions\InternalErrorException;

describe('InternalErrorException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates an internal error exception', function (): void {
            $exception = new Exception('Test error', 500);
            $requestException = InternalErrorException::create($exception);

            expect($requestException)->toBeInstanceOf(AbstractRequestException::class);
            expect($requestException->toArray())->toMatchSnapshot();
            expect($requestException->getErrorCode())->toBe(ErrorCode::InternalError->value);
            expect($requestException->getErrorMessage())->toBe('Internal error');
            expect($requestException->getErrorDetails())->toMatchArray([
                [
                    'status' => '500',
                    'title' => 'Internal error',
                    'detail' => 'Test error',
                ],
            ]);
        });
    });
});

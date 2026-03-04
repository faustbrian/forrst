<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Exceptions\AbstractRequestException;
use Cline\Forrst\Exceptions\FunctionNotFoundException;

describe('FunctionNotFoundException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates a method not found exception', function (): void {
            $requestException = FunctionNotFoundException::create();

            expect($requestException)->toBeInstanceOf(AbstractRequestException::class);
            expect($requestException->toArray())->toMatchSnapshot();
            expect($requestException->getErrorCode())->toBe(ErrorCode::FunctionNotFound->value);
            expect($requestException->getErrorMessage())->toBe('Function not found');
        });
    });
});

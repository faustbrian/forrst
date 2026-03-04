<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Exceptions\AbstractRequestException;
use Cline\Forrst\Exceptions\InvalidSortsException;

describe('InvalidSortsException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates an invalid filters exception', function (): void {
            $requestException = InvalidSortsException::create(
                [
                    [
                        'attribute' => 'sort1',
                        'direction' => 'asc',
                    ],
                ],
                ['sort2', 'sort3'],
            );

            expect($requestException)->toBeInstanceOf(AbstractRequestException::class);
            expect($requestException->toArray())->toMatchSnapshot();
            expect($requestException->getErrorCode())->toBe(ErrorCode::InvalidArguments->value);
            expect($requestException->getErrorMessage())->toBe('Invalid arguments');
        });
    });
});

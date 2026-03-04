<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Exceptions\AbstractRequestException;
use Cline\Forrst\Exceptions\JsonSchemaException;

describe('JsonSchemaException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates exception for invalid rule', function (): void {
            $requestException = JsonSchemaException::invalidRule('customRule');

            expect($requestException)->toBeInstanceOf(AbstractRequestException::class);
            expect($requestException->toArray())->toMatchSnapshot();
            expect($requestException->getErrorCode())->toBe(ErrorCode::InternalError->value);
            expect($requestException->getErrorMessage())->toBe('Internal error');
        });
    });
});

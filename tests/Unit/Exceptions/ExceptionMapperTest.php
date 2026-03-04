<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Exceptions\ExceptionMapper;
use Cline\Forrst\Exceptions\InternalErrorException;

describe('ExceptionMapper', function (): void {
    test('maps exception to Forrst exception', function (): void {
        $exception = new Exception('Test error');
        $mapped = ExceptionMapper::execute($exception);

        expect($mapped)->toBeInstanceOf(InternalErrorException::class);
    });
});

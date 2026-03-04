<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Exceptions;

use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Exceptions\OperationFailedException;

use function describe;
use function expect;
use function test;

describe('OperationFailedException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates exception with operation id and custom reason', function (): void {
            $exception = OperationFailedException::create('op-123', 'Connection timeout');

            expect($exception)->toBeInstanceOf(OperationFailedException::class)
                ->and($exception->getMessage())->toBe('Connection timeout')
                ->and($exception->getErrorCode())->toBe(ErrorCode::AsyncOperationFailed->value)
                ->and($exception->getErrorDetails())->toBe(['operation_id' => 'op-123']);
        });

        test('creates exception with default message when reason is null', function (): void {
            $exception = OperationFailedException::create('op-456');

            expect($exception)->toBeInstanceOf(OperationFailedException::class)
                ->and($exception->getMessage())->toBe("Operation 'op-456' failed")
                ->and($exception->getErrorCode())->toBe(ErrorCode::AsyncOperationFailed->value)
                ->and($exception->getErrorDetails())->toBe(['operation_id' => 'op-456']);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty operation id', function (): void {
            $exception = OperationFailedException::create('');

            expect($exception->getMessage())->toBe("Operation '' failed")
                ->and($exception->getErrorDetails())->toBe(['operation_id' => '']);
        });

        test('handles empty reason string', function (): void {
            $exception = OperationFailedException::create('op-789', '');

            expect($exception->getMessage())->toBe('')
                ->and($exception->getErrorDetails())->toBe(['operation_id' => 'op-789']);
        });
    });
});

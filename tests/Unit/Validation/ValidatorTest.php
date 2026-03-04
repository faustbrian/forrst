<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Validation\Validator;
use Illuminate\Validation\ValidationException;

describe('Validator', function (): void {
    test('validates data successfully', function (): void {
        Validator::validate(['email' => 'test@example.com'], ['email' => 'required|email']);
        expect(true)->toBeTrue();
    });

    test('throws exception on validation failure', function (): void {
        Validator::validate(['email' => 'invalid'], ['email' => 'required|email']);
    })->throws(ValidationException::class);
});

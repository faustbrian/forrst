<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Functions\Concerns\InteractsWithQueryBuilder;

describe('InteractsWithQueryBuilder', function (): void {
    test('trait exists and can be used', function (): void {
        $trait = new ReflectionClass(InteractsWithQueryBuilder::class);
        expect($trait->isTrait())->toBeTrue();
    });
});

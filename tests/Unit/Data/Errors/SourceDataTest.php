<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\Errors\SourceData;

describe('SourceData', function (): void {
    describe('Happy Paths', function (): void {
        test('creates instance with pointer', function (): void {
            $data = SourceData::from([
                'pointer' => '/data/attributes/email',
            ]);

            expect($data)->toBeInstanceOf(SourceData::class)
                ->and($data->pointer)->toBe('/data/attributes/email');
        });

        test('creates instance with position', function (): void {
            $data = SourceData::from([
                'position' => 42,
            ]);

            expect($data)->toBeInstanceOf(SourceData::class)
                ->and($data->position)->toBe(42);
        });

        test('creates instance with both pointer and position', function (): void {
            $data = SourceData::from([
                'pointer' => '/data/attributes/name',
                'position' => 100,
            ]);

            expect($data)->toBeInstanceOf(SourceData::class)
                ->and($data->pointer)->toBe('/data/attributes/name')
                ->and($data->position)->toBe(100);
        });

        test('creates instance with pointer using static factory method', function (): void {
            $data = SourceData::pointer('/call/arguments/customer_id');

            expect($data)->toBeInstanceOf(SourceData::class)
                ->and($data->pointer)->toBe('/call/arguments/customer_id')
                ->and($data->position)->toBeNull();
        });

        test('creates instance with position using static factory method', function (): void {
            $data = SourceData::position(256);

            expect($data)->toBeInstanceOf(SourceData::class)
                ->and($data->position)->toBe(256)
                ->and($data->pointer)->toBeNull();
        });

        test('serializes to array with only pointer when using pointer factory', function (): void {
            $data = SourceData::pointer('/data/attributes/email');

            expect($data->toArray())->toBe(['pointer' => '/data/attributes/email']);
        });

        test('serializes to array with only position when using position factory', function (): void {
            $data = SourceData::position(42);

            expect($data->toArray())->toBe(['position' => 42]);
        });

        test('serializes to array with both pointer and position when both are set', function (): void {
            $data = new SourceData(
                pointer: '/data/relationships/author',
                position: 128,
            );

            expect($data->toArray())->toBe([
                'pointer' => '/data/relationships/author',
                'position' => 128,
            ]);
        });

        test('serializes to empty array when neither pointer nor position is set', function (): void {
            $data = new SourceData();

            expect($data->toArray())->toBe([]);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles zero position using position factory', function (): void {
            $data = SourceData::position(0);

            expect($data->position)->toBe(0)
                ->and($data->pointer)->toBeNull()
                ->and($data->toArray())->toBe(['position' => 0]);
        });

        test('handles empty string pointer using pointer factory', function (): void {
            $data = SourceData::pointer('');

            expect($data->pointer)->toBe('')
                ->and($data->position)->toBeNull()
                ->and($data->toArray())->toBe(['pointer' => '']);
        });

        test('handles complex JSON pointer paths with special characters', function (): void {
            $data = SourceData::pointer('/data/0/attributes/~0tilde~1slash');

            expect($data->pointer)->toBe('/data/0/attributes/~0tilde~1slash')
                ->and($data->toArray())->toBe(['pointer' => '/data/0/attributes/~0tilde~1slash']);
        });

        test('handles large position values', function (): void {
            $data = SourceData::position(999_999);

            expect($data->position)->toBe(999_999)
                ->and($data->toArray())->toBe(['position' => 999_999]);
        });
    });
});

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\Configuration\ServerData;

describe('ServerData', function (): void {
    test('creates instance from array', function (): void {
        $data = ServerData::from([
            'name' => 'test',
            'path' => '/rpc',
            'route' => '/rpc',
            'version' => '1.0',
            'middleware' => [],
            'methods' => null,
        ]);
        expect($data)->toBeInstanceOf(ServerData::class)
            ->and($data->name)->toBe('test')
            ->and($data->version)->toBe('1.0');
    });
});

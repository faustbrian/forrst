<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\ProtocolData;
use Illuminate\Support\Facades\Route;
use Tests\Support\Fakes\Server;

use function Cline\Forrst\post_forrst;

describe('Helper Functions', function (): void {
    beforeEach(function (): void {
        Route::rpc(Server::class);
    });

    test('post_forrst sends Forrst request', function (): void {
        if (!function_exists('Cline\Forrst\post_forrst')) {
            $this->markTestSkipped('post_forrst function not available');
        }

        $response = post_forrst('urn:app:forrst:fn:subtract', ['minuend' => 42, 'subtrahend' => 23]);

        $response->assertOk();
        $response->assertJson(['protocol' => ProtocolData::forrst()->toArray()]);
    });

    test('post_forrst sends request with custom id', function (): void {
        if (!function_exists('Cline\Forrst\post_forrst')) {
            $this->markTestSkipped('post_forrst function not available');
        }

        $response = post_forrst('urn:app:forrst:fn:sum', ['data' => [1, 2, 3]], null, 'custom-request-id');

        $response->assertOk();
        $response->assertJsonPath('id', 'custom-request-id');
    });

    test('post_forrst sends request with function version', function (): void {
        if (!function_exists('Cline\Forrst\post_forrst')) {
            $this->markTestSkipped('post_forrst function not available');
        }

        $response = post_forrst('urn:app:forrst:fn:get-data', null, '1');

        $response->assertOk();
        $response->assertJson(['protocol' => ProtocolData::forrst()->toArray()]);
    });
});

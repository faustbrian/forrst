<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Support\Facades\Route;
use Tests\Support\Fakes\Server;

test('capture forrst.describe response', function (): void {
    Route::rpc(Server::class);

    $response = $this->call('POST', '/rpc', [], [], [], [], json_encode([
        'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
        'id' => '1',
        'call' => [
            'function' => 'urn:cline:forrst:ext:discovery:fn:describe',
        ],
    ]));

    file_put_contents('/tmp/forrst_describe_response.json', $response->getContent());

    expect($response->status())->toBe(200);
});

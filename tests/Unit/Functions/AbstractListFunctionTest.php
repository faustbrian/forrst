<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\CallData;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Repositories\ResourceRepository;
use Tests\Support\Fakes\Functions\ListUsers;
use Tests\Support\Models\User;
use Tests\Support\Resources\UserResource;

describe('AbstractListFunction', function (): void {
    beforeEach(function (): void {
        ResourceRepository::register(User::class, UserResource::class);
    });

    test('creates list method instance', function (): void {
        $method = new ListUsers();

        expect($method)->toBeInstanceOf(ListUsers::class);
        expect($method->getUrn())->toBe('urn:app:forrst:fn:users:list');
    });

    test('handles list request', function (): void {
        User::query()->create(['name' => 'John Doe', 'created_at' => now(), 'updated_at' => now()]);
        User::query()->create(['name' => 'Jane Doe', 'created_at' => now(), 'updated_at' => now()]);

        $method = new ListUsers();
        $request = RequestObjectData::from([
            'protocol' => ProtocolData::forrst()->toArray(),
            'id' => '1',
            'call' => CallData::from([
                'function' => 'urn:app:forrst:fn:users:list',
            ]),
        ]);

        $method->setRequest($request);
        $result = $method->handle();

        expect($result)->toBeObject();
        expect($result->data)->toBeArray();
    });

    test('gets arguments configuration', function (): void {
        $method = new ListUsers();
        $arguments = $method->getArguments();

        expect($arguments)->toBeArray();
        expect($arguments)->not()->toBeEmpty();
    });
});

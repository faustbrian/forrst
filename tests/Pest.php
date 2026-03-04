<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// use Saloon\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockClient;
use Tests\TestCase;

// Load helper functions for testing
require_once __DIR__.'/../src/helpers.php';

uses(
    TestCase::class,
    RefreshDatabase::class,
)->beforeEach(function (): void {
    MockClient::destroyGlobal();

    // Config::preventStrayRequests();
})->in(__DIR__);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
 */

expect()->extend('toBeJsonSerialized', function (array|string $expected): void {
    expect(json_decode(json_encode($this->value->jsonSerialize()), true))->toBe($expected);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
 */

function jsonFixture(string $path, string $file): array
{
    return json_decode(file_get_contents(sprintf('%s/Fixtures/%s.json', $path, $file)), true, 512, \JSON_THROW_ON_ERROR);
}

function xmlFixture(string $path, string $file): string
{
    return file_get_contents(sprintf('%s/Fixtures/%s.xml', $path, $file));
}

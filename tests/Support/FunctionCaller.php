<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support;

use Illuminate\Support\Facades\URL;

use const JSON_THROW_ON_ERROR;

use function count;
use function file_get_contents;
use function json_decode;
use function Pest\Laravel\call;
use function realpath;
use function sprintf;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class FunctionCaller
{
    public static function call(string $path, int $statusCode = 200): void
    {
        $request = file_get_contents(realpath(__DIR__.sprintf('/Fixtures/Requests/%s.json', $path)));
        $responseFixture = file_get_contents(realpath(__DIR__.sprintf('/Fixtures/Responses/%s.json', $path)));
        $expected = json_decode($responseFixture, true, 512, JSON_THROW_ON_ERROR);

        $response = call('POST', URL::to('/rpc'), [], [], [], [], $request)
            ->assertStatus($statusCode)
            ->assertHeader('Content-Type', 'application/json');

        $actual = $response->json();

        // For error responses, the ID might be auto-generated, so we update expected to match actual ID
        $hasErrors = isset($actual['errors']) && count($actual['errors']) > 0;

        if ($hasErrors && isset($actual['id'], $expected['id'])) {
            $expected['id'] = $actual['id'];
        }

        // Normalize duration values since they vary with execution time
        if (isset($expected['meta']['duration']['value'], $actual['meta']['duration']['value'])) {
            $expected['meta']['duration']['value'] = $actual['meta']['duration']['value'];
        }

        $response->assertJson($expected);
    }
}

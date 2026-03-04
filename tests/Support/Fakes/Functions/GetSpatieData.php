<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fakes\Functions;

use Cline\Forrst\Functions\AbstractFunction;
use Tests\Support\TestSpatieData;

/**
 * Test method that returns a Spatie Data object to test line 75 of MethodController.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class GetSpatieData extends AbstractFunction
{
    public function handle(): TestSpatieData
    {
        return new TestSpatieData(
            id: 42,
            name: 'Test Item',
            email: 'test@example.com',
        );
    }
}

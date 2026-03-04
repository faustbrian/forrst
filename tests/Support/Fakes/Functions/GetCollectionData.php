<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fakes\Functions;

use Cline\Forrst\Functions\AbstractFunction;
use Illuminate\Support\Collection;

/**
 * Test method that returns a Collection to test line 71 of MethodController.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class GetCollectionData extends AbstractFunction
{
    public function handle(): Collection
    {
        return new Collection([
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
            ['id' => 3, 'name' => 'Item 3'],
        ]);
    }
}

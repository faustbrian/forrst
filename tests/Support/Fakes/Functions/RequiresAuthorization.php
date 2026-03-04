<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fakes\Functions;

use Cline\Forrst\Functions\AbstractFunction;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Test method that always throws an AuthorizationException.
 * Used to test authorization error handling in RequestHandler.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RequiresAuthorization extends AbstractFunction
{
    public function handle(): never
    {
        throw new AuthorizationException('User not authorized');
    }
}

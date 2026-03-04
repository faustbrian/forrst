<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fakes;

use Cline\Forrst\Functions\Concerns\InteractsWithAuthentication;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Test class that uses the InteractsWithAuthentication trait for testing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class AuthenticationTestClass
{
    use InteractsWithAuthentication;

    /**
     * Public wrapper for testing the protected getCurrentUser method.
     */
    public function testGetCurrentUser(): Authenticatable
    {
        return $this->getCurrentUser();
    }
}

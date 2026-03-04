<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fakes;

use Cline\Forrst\Servers\AbstractServer;
use Illuminate\Auth\AuthenticationException;
use Override;

/**
 * Server that throws AuthenticationException during method repository access.
 * Used to test outer catch block in RequestHandler (lines 162-166).
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class AuthExceptionServer extends AbstractServer
{
    #[Override()]
    public function functions(): array
    {
        throw new AuthenticationException('Authentication required');
    }

    #[Override()]
    public function validate(): void
    {
        // No validation needed for test fake
    }
}

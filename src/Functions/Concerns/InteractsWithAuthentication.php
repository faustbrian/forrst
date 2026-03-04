<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Functions\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function abort_unless;
use function auth;

/**
 * Authentication helper trait for Forrst functions.
 *
 * Provides convenient methods for retrieving and verifying authenticated users
 * within Forrst function handlers. Integrates with Laravel's authentication system
 * to access the current user and enforce authentication requirements.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/system-functions
 */
trait InteractsWithAuthentication
{
    /**
     * Get the currently authenticated user or abort with 401 Unauthorized.
     *
     * Retrieves the authenticated user from Laravel's auth guard. If no user is
     * authenticated, immediately aborts the request with a 401 Unauthorized HTTP
     * exception. Use this method when authentication is mandatory for the operation.
     *
     * @throws HttpException   When no user is authenticated (HTTP 401)
     * @return Authenticatable The authenticated user instance
     */
    protected function getCurrentUser(): Authenticatable
    {
        /** @var Guard $guard */
        $guard = auth();
        abort_unless($guard->check(), 401, 'Unauthorized');

        /** @var Authenticatable */
        return $guard->user();
    }
}

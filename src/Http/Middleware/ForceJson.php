<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Enforces JSON content type for Forrst RPC endpoints.
 *
 * Automatically sets Content-Type and Accept headers to application/json for
 * requests to Forrst routes, ensuring consistent API behavior regardless of
 * client headers and preventing content negotiation issues. This guarantees
 * that Laravel processes requests as JSON and returns JSON responses.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/protocol
 */
final class ForceJson
{
    /**
     * Force JSON content type headers for Forrst routes.
     *
     * Intercepts requests to /rpc and /rpc/* paths and sets the Content-Type
     * and Accept headers to application/json, ensuring the Laravel application
     * processes the request as JSON and returns JSON responses. This prevents
     * issues where client-provided headers might cause Laravel to use incorrect
     * content negotiation.
     *
     * @param Request $request The incoming HTTP request to process
     * @param Closure $next    The next middleware in the pipeline
     *
     * @return mixed The response from the next middleware or handler
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->is('rpc') || $request->is('rpc/*')) {
            $request->headers->set('Content-Type', 'application/json');
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}

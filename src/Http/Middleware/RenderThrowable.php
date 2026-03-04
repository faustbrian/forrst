<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Http\Middleware;

use Cline\Forrst\Exceptions\ErrorRenderer;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function throw_if;

/**
 * Renders exceptions as Forrst protocol-compliant error responses.
 *
 * Intercepts uncaught exceptions in the request lifecycle and automatically
 * converts them to Forrst protocol compliant error responses. Ensures that all
 * errors, whether from the RPC layer or underlying application, are formatted
 * consistently according to the Forrst specification. If the error renderer cannot
 * produce a valid JSON response, the original exception is re-thrown.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/errors
 *
 * @psalm-immutable
 */
final readonly class RenderThrowable
{
    /**
     * Handle an incoming request with exception rendering.
     *
     * Wraps the request in a try-catch block to intercept any thrown exceptions
     * and render them as Forrst error responses. If the ErrorRenderer cannot produce
     * a valid JsonResponse, the original exception is re-thrown to allow Laravel's
     * default exception handler to process it.
     *
     * @param Request                   $request HTTP request instance
     * @param Closure(Request):Response $next    Next middleware in the chain
     *
     * @throws Throwable When the error renderer fails to produce a valid JSON response
     *
     * @return Response HTTP response, either successful or error response
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (Throwable $throwable) {
            $response = ErrorRenderer::render($throwable, $request);

            throw_if(!$response instanceof JsonResponse, $throwable);

            return $response;
        }
    }
}

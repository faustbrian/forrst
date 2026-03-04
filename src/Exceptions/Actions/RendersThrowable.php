<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions\Actions;

use Cline\Forrst\Exceptions\ErrorRenderer;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Action class for registering Forrst error renderers.
 *
 * Part of the Forrst protocol error rendering actions. Configures Laravel's exception
 * handler to render exceptions as Forrst protocol compliant error responses.
 * Automatically transforms standard Laravel exceptions into appropriate Forrst error
 * formats when the request expects JSON responses.
 *
 * This action is typically invoked during application bootstrap to integrate Forrst
 * error handling into the Laravel exception pipeline. It ensures all exceptions
 * thrown during RPC request handling are properly formatted according to the Forrst
 * error specification.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/errors
 *
 * @psalm-immutable
 */
final readonly class RendersThrowable
{
    /**
     * Register the Forrst exception renderer.
     *
     * Configures the Laravel exception handler to intercept exceptions and render
     * them as Forrst protocol error responses for JSON requests. The renderer maps
     * Laravel exceptions to Forrst exception types via the ExceptionMapper, formats
     * them with proper error codes and messages, and returns appropriate HTTP
     * status codes.
     *
     * @param Exceptions $exceptions The Laravel exception configuration instance to register
     *                               the renderer with. Modified to include Forrst error
     *                               rendering logic for JSON API requests. This is typically
     *                               the application's exception handler configuration.
     */
    public static function execute(Exceptions $exceptions): void
    {
        $exceptions->renderable(
            fn (Throwable $exception, Request $request): ?JsonResponse => ErrorRenderer::render($exception, $request),
        );
    }
}

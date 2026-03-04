<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions\Concerns;

use Cline\Forrst\Exceptions\ErrorRenderer;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Trait for adding Forrst error rendering to exception handlers.
 *
 * Part of the Forrst protocol error rendering trait. Provides a convenient method
 * to configure Forrst protocol compliant error rendering in Laravel exception handlers.
 * Designed to be mixed into classes that extend Laravel's Exceptions configuration
 * for easy integration of Forrst error handling.
 *
 * This trait is useful when you want to add Forrst error rendering to a custom
 * exception handler class rather than using the standalone action class. Simply
 * use this trait in your exception handler and call renderableThrowable() during
 * handler configuration.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/errors
 *
 * @mixin Exceptions
 *
 * @phpstan-ignore trait.unused (This trait is meant for package consumers, not used internally)
 */
trait RendersThrowable
{
    /**
     * Register Forrst exception rendering.
     *
     * Configures the exception handler to intercept and render exceptions as Forrst
     * protocol error responses for JSON requests. Maps Laravel exceptions to Forrst
     * exception types via ExceptionMapper and formats them with appropriate error
     * codes, messages, and HTTP status codes.
     *
     * Call this method from your exception handler's configuration to enable Forrst
     * error handling for all JSON requests.
     */
    protected function renderableThrowable(): void
    {
        $this->renderable(
            fn (Throwable $exception, Request $request): ?JsonResponse => ErrorRenderer::render($exception, $request),
        );
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Cline\Forrst\Data\ProtocolData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

use function is_string;
use function response;

/**
 * Renders exceptions as Forrst protocol error responses.
 *
 * Part of the Forrst protocol error response renderer. Provides the core logic for
 * converting exceptions into Forrst protocol compliant error responses. This class
 * is shared between the middleware and the exception handler trait/action to ensure
 * consistent error formatting across the application.
 *
 * The renderer handles exception mapping via ExceptionMapper, error serialization,
 * and response formatting with proper HTTP status codes and headers. It only
 * renders errors for JSON requests to avoid interfering with traditional HTML
 * error pages.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/errors
 *
 * @psalm-immutable
 */
final readonly class ErrorRenderer
{
    /**
     * Render an exception as a Forrst error response.
     *
     * Converts the exception to a Forrst exception via ExceptionMapper, then formats
     * it as a Forrst protocol error response with appropriate HTTP status code and
     * headers. Only renders if the request expects JSON responses to avoid
     * interfering with HTML error pages.
     *
     * The response structure includes the protocol version, request ID, null result,
     * and an array of error objects. If no request ID is available, a ULID is
     * generated to ensure all responses have unique identifiers.
     *
     * @param  Throwable         $exception The exception to render into a Forrst error response.
     *                                      Will be mapped to an appropriate Forrst exception type
     *                                      via ExceptionMapper if not already a Forrst exception.
     * @param  Request           $request   The HTTP request being handled. Used to check if JSON
     *                                      response is expected and to extract the request ID
     *                                      for correlation with the error response.
     * @return null|JsonResponse The Forrst formatted error response with HTTP status code and
     *                           headers, or null if the request does not expect JSON
     */
    public static function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (!$request->wantsJson()) {
            return null;
        }

        $exception = ExceptionMapper::execute($exception);

        $id = $request->input('id');

        if (!is_string($id) || $id === '') {
            $id = Str::ulid()->toString();
        }

        $response = [
            'protocol' => ProtocolData::forrst()->toArray(),
            'id' => $id,
            'result' => null,
            'errors' => [$exception->toArray()],
        ];

        /** @var JsonResponse */
        return response()->json(
            $response,
            $exception->getStatusCode(),
            $exception->getHeaders(),
        );
    }
}

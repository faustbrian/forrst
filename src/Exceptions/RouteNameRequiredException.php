<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Exception thrown when a Forrst route is missing a required name.
 *
 * Thrown by the BootServer middleware when attempting to initialize a Forrst server
 * instance but the Laravel route lacks a name identifier. Route names are required
 * for server lookup and configuration resolution in multi-server setups. This is a
 * configuration error that occurs during request bootstrapping, before the RPC
 * protocol layer is reached. Returns HTTP 400 Bad Request to indicate a routing
 * configuration issue rather than a protocol-level error.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/protocol Protocol specification
 */
final class RouteNameRequiredException extends BadRequestHttpException implements RpcException
{
    /**
     * Create a route name required exception.
     *
     * @return self Exception instance with HTTP 400 status and message indicating
     *              that a named route is required for server bootstrapping
     */
    public static function create(): self
    {
        return new self('A route name is required to boot the server.');
    }
}

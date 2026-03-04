<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

/**
 * Marker interface for all Forrst RPC exceptions.
 *
 * Provides a common type for all exceptions thrown by the Forrst RPC package,
 * enabling consumers to catch and handle any Forrst-related exception with a
 * single catch block. All concrete exception classes in this package implement
 * this interface directly or indirectly through base exception classes.
 *
 * Implementing this interface ensures exceptions maintain compatibility with
 * PHP's standard exception hierarchy while providing Forrst-specific error
 * handling capabilities.
 *
 * ```php
 * try {
 *     $server->handle($request);
 * } catch (RpcException $e) {
 *     // Handle any Forrst RPC exception
 *     logger()->error('RPC error', ['exception' => $e]);
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 */
interface RpcException extends ForrstException
{
    // Marker interface - no methods required
}

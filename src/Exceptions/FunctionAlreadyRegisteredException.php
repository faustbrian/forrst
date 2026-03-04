<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use RuntimeException;

/**
 * Exception thrown when attempting to register a method name that already exists.
 *
 * Prevents duplicate method registration in the FunctionRepository, ensuring
 * each method name is unique within the registry. This is a development-time
 * exception that indicates a configuration or registration issue where the same
 * RPC method name is being registered multiple times.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/protocol Protocol specification
 */
final class FunctionAlreadyRegisteredException extends RuntimeException implements RpcException
{
    /**
     * Create exception for duplicate method registration.
     *
     * @param string $methodName The method name that was already registered in the
     *                           function repository. Used to identify which specific
     *                           method caused the duplicate registration conflict.
     *
     * @return self The created exception instance with a descriptive error message
     *              indicating the duplicate method name.
     */
    public static function forFunction(string $methodName): self
    {
        return new self('Function already registered: '.$methodName);
    }
}

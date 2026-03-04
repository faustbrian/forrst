<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use InvalidArgumentException;

/**
 * Exception thrown when RPC call data is missing the required function name.
 *
 * The function name is a mandatory field in Forrst RPC call requests that specifies
 * which server-side method to invoke. This exception is raised during request parsing
 * when the function name field is absent, preventing ambiguous or incomplete RPC calls.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/protocol
 * @see https://docs.cline.sh/forrst/errors
 */
final class MissingFunctionNameException extends InvalidArgumentException implements RpcException
{
    /**
     * Creates an exception for a missing function name in call data.
     *
     * @return self a new exception instance with a descriptive error message
     */
    public static function create(): self
    {
        return new self('Function name is required');
    }
}

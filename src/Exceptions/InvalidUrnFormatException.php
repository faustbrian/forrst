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
 * Exception thrown when a URN string has an invalid format.
 *
 * URNs must follow the format "urn:namespace:resource" with at least
 * three colon-separated segments starting with "urn".
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidUrnFormatException extends InvalidArgumentException implements RpcException
{
    /**
     * Create exception for invalid URN format.
     *
     * @param string $urn The invalid URN string that was provided
     *
     * @return self New exception instance
     */
    public static function create(string $urn): self
    {
        return new self('Invalid URN format: '.$urn);
    }
}

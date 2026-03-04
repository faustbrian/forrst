<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use function sprintf;

/**
 * Exception thrown when a URL uses an unsupported protocol.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidProtocolException extends ValidationException
{
    public static function forUrl(string $field): self
    {
        return new self(sprintf('%s must use http or https protocol', $field));
    }
}

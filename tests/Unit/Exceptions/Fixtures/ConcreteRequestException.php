<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Exceptions\Fixtures;

use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Exceptions\AbstractRequestException;

/**
 * Concrete test implementation of AbstractRequestException.
 *
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class ConcreteRequestException extends AbstractRequestException
{
    public static function make(ErrorCode $code, string $message, ?array $details = null): self
    {
        return self::new($code, $message, details: $details);
    }
}

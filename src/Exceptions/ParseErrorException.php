<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Cline\Forrst\Enums\ErrorCode;

/**
 * Exception thrown when the Forrst request payload cannot be parsed.
 *
 * Represents a Forrst PARSE_ERROR (-32700) that occurs when the server receives
 * invalid JSON that cannot be deserialized. This is typically caused by malformed
 * JSON syntax in the request body, such as unclosed braces, trailing commas, or
 * invalid escape sequences. This error is thrown before request validation occurs,
 * as the payload structure itself is unparseable.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/errors Error handling specification
 * @see https://docs.cline.sh/forrst/protocol Protocol specification
 */
final class ParseErrorException extends AbstractRequestException
{
    /**
     * Create a parse error exception instance.
     *
     * @param null|array<int|string, mixed> $data Additional error details to include in the error
     *                                            response. Can contain diagnostic information about
     *                                            the parse failure, such as the specific JSON syntax
     *                                            error or the character position where parsing failed.
     *                                            Formatted according to JSON:API error specifications.
     *
     * @return self Forrst exception instance with PARSE_ERROR code (-32700)
     */
    public static function create(?array $data = null): self
    {
        // @phpstan-ignore-next-line argument.type
        return self::new(ErrorCode::ParseError, 'Parse error', details: $data);
    }
}

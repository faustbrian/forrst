<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Override;

/**
 * Base exception for all NOT_FOUND error scenarios in Forrst RPC.
 *
 * Provides a common foundation for resource-not-found exceptions, ensuring
 * consistent HTTP 404 status codes across all derived exception types.
 * Subclasses should implement specific factory methods for different
 * resource types (operations, methods, etc.).
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/errors
 */
abstract class NotFoundException extends AbstractRequestException
{
    /**
     * Returns the HTTP status code for not found errors.
     *
     * @return int always returns 404 (Not Found)
     */
    #[Override()]
    public function getStatusCode(): int
    {
        return 404;
    }
}

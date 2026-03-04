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
 * Base exception for all data validation errors.
 *
 * Provides a common type for validation-related exceptions thrown during
 * DTO construction, configuration parsing, and input validation. Consumers
 * can catch this interface to handle any validation error.
 * @author Brian Faust <brian@cline.sh>
 */
abstract class ValidationException extends InvalidArgumentException implements ForrstException
{
    // Abstract base - no factory methods
}

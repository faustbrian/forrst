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
 * Base exception for configuration-related errors.
 *
 * Thrown when server configuration, extension configuration, or other
 * system configuration is invalid or missing required values.
 * @author Brian Faust <brian@cline.sh>
 */
abstract class ConfigurationException extends RuntimeException implements ForrstException
{
    // Abstract base - no factory methods
}

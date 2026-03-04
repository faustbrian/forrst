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
 * Exception thrown when a configuration value is invalid.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidConfigurationException extends ConfigurationException
{
    public static function forKey(string $key, string $reason): self
    {
        return new self(sprintf('Invalid configuration for %s: %s', $key, $reason));
    }
}

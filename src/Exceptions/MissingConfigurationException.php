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
 * Exception thrown when a required configuration is missing.
 * @author Brian Faust <brian@cline.sh>
 */
final class MissingConfigurationException extends ConfigurationException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf('Missing required configuration: %s', $key));
    }
}

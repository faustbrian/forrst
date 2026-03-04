<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Cline\Forrst\Enums\ErrorCode;

use function sprintf;

/**
 * Exception thrown when no version exists at a requested stability level.
 *
 * Used when a client requests a version by stability level (alpha, beta, rc, stable)
 * but no versions exist at that stability level. The exception includes all available
 * versions to help the client understand what stability levels are supported.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/versioning
 * @see https://docs.cline.sh/forrst/errors
 */
final class StabilityVersionNotFoundException extends VersionNotFoundException
{
    /**
     * Create exception for stability level not found.
     *
     * @param string        $function          Function name that was requested
     * @param string        $stability         Stability level that was requested (alpha, beta, rc, stable)
     * @param array<string> $availableVersions List of all registered versions for this function
     *
     * @return self New exception instance with VERSION_NOT_FOUND error code
     */
    public static function create(string $function, string $stability, array $availableVersions): self
    {
        $message = sprintf('No %s version found for function %s', $stability, $function);

        return self::new(
            ErrorCode::VersionNotFound,
            $message,
            details: [
                'function' => $function,
                'requested_stability' => $stability,
                'available_versions' => $availableVersions,
            ],
        );
    }
}

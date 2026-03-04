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
 * Exception thrown when a specific version string does not exist.
 *
 * Used when a client requests an exact version like "1.2.3" that is not
 * registered in the function repository. The exception includes all available
 * versions to assist the client in selecting a valid alternative version.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/versioning
 * @see https://docs.cline.sh/forrst/errors
 */
final class ExactVersionNotFoundException extends VersionNotFoundException
{
    /**
     * Create exception for exact version not found.
     *
     * @param string        $function          Function name that was requested
     * @param string        $requestedVersion  Specific version string that was requested
     * @param array<string> $availableVersions List of all registered versions for this function
     *
     * @return self New exception instance with VERSION_NOT_FOUND error code
     */
    public static function create(string $function, string $requestedVersion, array $availableVersions): self
    {
        $message = sprintf('Version %s not found for function %s', $requestedVersion, $function);

        return self::new(
            ErrorCode::VersionNotFound,
            $message,
            details: [
                'function' => $function,
                'requested_version' => $requestedVersion,
                'available_versions' => $availableVersions,
            ],
        );
    }
}

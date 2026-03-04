<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use RuntimeException;

use function sprintf;
use function str_starts_with;

/**
 * Exception thrown when attempting to register a method in a reserved namespace.
 *
 * Per Forrst protocol specification, certain namespaces are reserved for internal
 * system use and cannot be used by application-defined methods. The "forrst.*"
 * namespace is reserved for protocol discovery methods and system functionality.
 * This exception is thrown during server registration to prevent naming conflicts
 * and ensure protocol compliance. This is a configuration-time error, not a
 * runtime request error.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/protocol Protocol specification
 */
final class ReservedNamespaceException extends RuntimeException implements RpcException
{
    /**
     * Namespace prefixes reserved for internal system use.
     *
     * These prefixes cannot be used by application-defined methods. The "urn:forrst:"
     * namespace is reserved for protocol discovery methods like urn:forrst:forrst:fn:describe
     * and other system-level functionality defined by the Forrst specification.
     */
    public const array RESERVED_PREFIXES = [
        'urn:forrst:',
    ];

    /**
     * Create an exception for a reserved namespace violation.
     *
     * @param string $functionName Fully qualified method name that attempted to use a
     *                             reserved namespace prefix (e.g., "forrst.customMethod").
     *                             Included in the error message for debugging.
     * @param string $namespace    Reserved namespace prefix that was violated (e.g., "forrst.").
     *                             Used to explain which namespace rule was broken.
     *
     * @return self Exception instance with descriptive message indicating the violation
     */
    public static function forFunction(string $functionName, string $namespace): self
    {
        return new self(sprintf(
            'Function "%s" cannot be registered: namespace "%s" is reserved for system use',
            $functionName,
            $namespace,
        ));
    }

    /**
     * Check if a method name violates reserved namespace rules.
     *
     * Iterates through all reserved namespace prefixes to determine if the provided
     * method name starts with any reserved prefix. Used during method registration
     * to validate namespace compliance before adding methods to the server.
     *
     * @param string $functionName Method name to validate against reserved namespace rules
     *
     * @return null|string The violated reserved prefix (e.g., "forrst.") if a violation
     *                     is detected, or null if the name is valid and does not conflict
     *                     with any reserved namespace
     */
    public static function getViolatedPrefix(string $functionName): ?string
    {
        foreach (self::RESERVED_PREFIXES as $prefix) {
            if (str_starts_with($functionName, $prefix)) {
                return $prefix;
            }
        }

        return null;
    }
}

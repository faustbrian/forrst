<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Functions;

use function array_map;
use function preg_match;

/**
 * Standard Forrst function URNs.
 *
 * Defines the URN constants for all core system functions in the Forrst protocol.
 * System functions provide protocol-level capabilities like discovery, health checks,
 * and operation management. Each URN uniquely identifies a function's behavior
 * contract as defined in the protocol specification.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/system-functions
 */
enum FunctionUrn: string
{
    /**
     * Ping function for connectivity checks.
     *
     * @see https://docs.cline.sh/forrst/system-functions#ping
     */
    case Ping = 'urn:cline:forrst:ext:diagnostics:fn:ping';

    /**
     * Capabilities discovery function.
     *
     * @see https://docs.cline.sh/forrst/system-functions#capabilities
     */
    case Capabilities = 'urn:cline:forrst:ext:discovery:fn:capabilities';

    /**
     * Function description/discovery function.
     *
     * @see https://docs.cline.sh/forrst/system-functions#describe
     */
    case Describe = 'urn:cline:forrst:ext:discovery:fn:describe';

    /**
     * Health check function.
     *
     * @see https://docs.cline.sh/forrst/system-functions#health
     */
    case Health = 'urn:cline:forrst:ext:diagnostics:fn:health';

    /**
     * Request cancellation function (cancellation extension).
     *
     * @see https://docs.cline.sh/forrst/extensions/cancellation
     */
    case Cancel = 'urn:cline:forrst:ext:cancellation:fn:cancel';

    /**
     * Operation status check function (async extension).
     *
     * @see https://docs.cline.sh/forrst/extensions/async
     */
    case OperationStatus = 'urn:cline:forrst:ext:async:fn:status';

    /**
     * Operation cancellation function (async extension).
     *
     * @see https://docs.cline.sh/forrst/extensions/async
     */
    case OperationCancel = 'urn:cline:forrst:ext:async:fn:cancel';

    /**
     * Operation listing function (async extension).
     *
     * @see https://docs.cline.sh/forrst/extensions/async
     */
    case OperationList = 'urn:cline:forrst:ext:async:fn:list';

    /**
     * Lock status check function (atomic-lock extension).
     *
     * @see https://docs.cline.sh/forrst/extensions/atomic-lock
     */
    case LocksStatus = 'urn:cline:forrst:ext:atomic-lock:fn:status';

    /**
     * Lock release function (atomic-lock extension).
     *
     * @see https://docs.cline.sh/forrst/extensions/atomic-lock
     */
    case LocksRelease = 'urn:cline:forrst:ext:atomic-lock:fn:release';

    /**
     * Force lock release function (atomic-lock extension).
     *
     * @see https://docs.cline.sh/forrst/extensions/atomic-lock
     */
    case LocksForceRelease = 'urn:cline:forrst:ext:atomic-lock:fn:force-release';

    /**
     * Get all standard function URNs as strings.
     *
     * @return array<int, string> Array of URN strings
     */
    public static function all(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * Check if a URN is a valid Forrst system function.
     *
     * @param string $urn The URN string to validate
     *
     * @return bool True if the URN is a standard function with valid format
     */
    public static function isSystem(string $urn): bool
    {
        // First check it's a valid system URN
        return self::tryFrom($urn) !== null;
    }

    /**
     * Validate URN format.
     *
     * @param string $urn The URN to validate
     *
     * @return bool True if format is valid
     */
    public static function isValidFormat(string $urn): bool
    {
        return (bool) preg_match(
            '/^urn:[a-z][a-z0-9-]*:forrst:(?:ext:[a-z0-9-]+:)?fn:[a-z0-9-]+:[a-z0-9-]+$/',
            $urn,
        );
    }
}

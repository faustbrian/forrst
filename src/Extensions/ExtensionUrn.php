<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions;

use function array_map;

/**
 * Standard Forrst extension URNs.
 *
 * Defines the URN constants for all standard extensions in the Forrst protocol.
 * Extensions allow clients and servers to negotiate optional behaviors like caching,
 * rate limiting, idempotency, and more. Each URN uniquely identifies an extension's
 * behavior contract as defined in the protocol specification.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/
 */
enum ExtensionUrn: string
{
    /**
     * Async operations extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/async
     */
    case Async = 'urn:cline:forrst:ext:async';

    /**
     * Response caching extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/caching
     */
    case Caching = 'urn:cline:forrst:ext:caching';

    /**
     * Request deadline extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/deadline
     */
    case Deadline = 'urn:cline:forrst:ext:deadline';

    /**
     * Deprecation warnings extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/deprecation
     */
    case Deprecation = 'urn:cline:forrst:ext:deprecation';

    /**
     * Dry-run validation extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/dry-run
     */
    case DryRun = 'urn:cline:forrst:ext:dry-run';

    /**
     * Idempotency extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/idempotency
     */
    case Idempotency = 'urn:cline:forrst:ext:idempotency';

    /**
     * Maintenance mode extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/maintenance
     */
    case Maintenance = 'urn:cline:forrst:ext:maintenance';

    /**
     * Request priority extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/priority
     */
    case Priority = 'urn:cline:forrst:ext:priority';

    /**
     * Request replay extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/replay
     */
    case Replay = 'urn:cline:forrst:ext:replay';

    /**
     * Query extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/query
     */
    case Query = 'urn:cline:forrst:ext:query';

    /**
     * Quota management extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/quota
     */
    case Quota = 'urn:cline:forrst:ext:quota';

    /**
     * Rate limit extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/rate-limit
     */
    case RateLimit = 'urn:cline:forrst:ext:rate-limit';

    /**
     * Locale/i18n extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/locale
     */
    case Locale = 'urn:cline:forrst:ext:locale';

    /**
     * Sensitive data redaction extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/redact
     */
    case Redact = 'urn:cline:forrst:ext:redact';

    /**
     * Distributed tracing extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/tracing
     */
    case Tracing = 'urn:cline:forrst:ext:tracing';

    /**
     * Retry guidance extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/retry
     */
    case Retry = 'urn:cline:forrst:ext:retry';

    /**
     * Request cancellation extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/cancellation
     */
    case Cancellation = 'urn:cline:forrst:ext:cancellation';

    /**
     * Simulation extension for demo/sandbox mode.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/simulation
     */
    case Simulation = 'urn:cline:forrst:ext:simulation';

    /**
     * Streaming extension (reserved).
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/stream
     */
    case Stream = 'urn:cline:forrst:ext:stream';

    /**
     * Atomic lock extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/atomic-lock
     */
    case AtomicLock = 'urn:cline:forrst:ext:atomic-lock';

    /**
     * Discovery extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/discovery
     */
    case Discovery = 'urn:cline:forrst:ext:discovery';

    /**
     * Diagnostics extension.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/diagnostics
     */
    case Diagnostics = 'urn:cline:forrst:ext:diagnostics';

    /**
     * Webhook extension for Standard Webhooks-compliant event notifications.
     *
     * @see https://docs.cline.sh/specs/forrst/extensions/webhook
     */
    case Webhook = 'urn:cline:forrst:ext:webhook';

    /**
     * Get all standard extension URNs as strings.
     *
     * Returns an array of all standard extension URN values. Useful for
     * capability negotiation, validation, and displaying available extensions.
     *
     * @return array<int, string> Array of URN strings
     */
    public static function all(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * Check if a URN is a standard extension.
     *
     * Validates whether the provided URN string corresponds to one of the
     * standard extensions defined in this enum. Returns false for custom
     * or unrecognized extension URNs.
     *
     * @param  string $urn The URN string to validate
     * @return bool   True if the URN is a standard extension, false otherwise
     */
    public static function isStandard(string $urn): bool
    {
        return self::tryFrom($urn) !== null;
    }
}

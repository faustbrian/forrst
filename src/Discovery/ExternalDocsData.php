<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Discovery;

use Cline\Forrst\Exceptions\InvalidProtocolException;
use Cline\Forrst\Exceptions\InvalidUrlException;
use Spatie\LaravelData\Data;

use const E_USER_WARNING;
use const FILTER_VALIDATE_URL;
use const PHP_URL_SCHEME;

use function filter_var;
use function in_array;
use function parse_url;
use function sprintf;
use function trigger_error;

/**
 * Reference to external documentation.
 *
 * Points to comprehensive documentation hosted outside the discovery document,
 * such as user guides, tutorials, or detailed API reference documentation.
 * Provides additional context and learning resources beyond the technical
 * specification contained in the discovery document.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/system-functions
 * @see https://docs.cline.sh/specs/forrst/discovery#external-documentation-object
 */
final class ExternalDocsData extends Data
{
    /**
     * Create a new external documentation reference.
     *
     * @param string      $url         Fully-qualified URL to the external documentation resource.
     *                                 Must be accessible via HTTP/HTTPS and should point to stable,
     *                                 versioned documentation that corresponds to this service version.
     *                                 Used by documentation tools and API explorers to link to additional
     *                                 learning resources.
     * @param null|string $description Optional human-readable description of what documentation
     *                                 is available at the URL (e.g., "Complete API Guide",
     *                                 "User Tutorial", "Migration Guide"). Helps users decide
     *                                 whether to follow the link based on their needs.
     */
    public function __construct(
        public readonly string $url,
        public readonly ?string $description = null,
    ) {
        $this->validateUrl($url);
    }

    /**
     * Validate URL is well-formed and uses HTTP/HTTPS protocol.
     *
     * @throws InvalidProtocolException If URL uses invalid protocol
     * @throws InvalidUrlException      If URL is malformed
     */
    private function validateUrl(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw InvalidUrlException::invalidFormat('url');
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw InvalidProtocolException::forUrl('url');
        }

        // Strongly recommend HTTPS for security
        if ($scheme === 'https') {
            return;
        }

        trigger_error(
            sprintf("Warning: External documentation URL should use HTTPS for security: '%s'", $url),
            E_USER_WARNING,
        );
    }
}

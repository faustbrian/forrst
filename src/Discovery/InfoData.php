<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Discovery;

use Cline\Forrst\Exceptions\EmptyFieldException;
use Cline\Forrst\Exceptions\FieldExceedsMaxLengthException;
use Cline\Forrst\Exceptions\InvalidProtocolException;
use Cline\Forrst\Exceptions\InvalidSemanticVersionException;
use Cline\Forrst\Exceptions\InvalidUrlException;
use Spatie\LaravelData\Data;

use const FILTER_VALIDATE_URL;

use function filter_var;
use function in_array;
use function mb_strlen;
use function mb_strtolower;
use function mb_trim;
use function parse_url;
use function preg_match;

/**
 * Service metadata for discovery documents.
 *
 * Provides human-readable identification and descriptive information about
 * the Forrst service. Displayed in API explorers, documentation generators,
 * and client tooling to help developers identify and understand the service.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/system-functions
 * @see https://docs.cline.sh/specs/forrst/discovery#info-object
 */
final class InfoData extends Data
{
    public readonly string $title;

    public readonly string $version;

    /**
     * Create a new service information object.
     *
     * @param string           $title          Human-readable name of the service (e.g., "Payment Processing API",
     *                                         "User Management Service"). Displayed prominently in API explorers
     *                                         and documentation to identify the service to developers and users.
     * @param string           $version        Semantic version number of the service API (e.g., "2.1.0"). Indicates
     *                                         the overall API version and compatibility level, distinct from individual
     *                                         function versions. Helps clients track API evolution and breaking changes.
     * @param null|string      $description    Optional detailed explanation of the service's purpose, capabilities,
     *                                         and scope. Provides context about what the service does, what problems
     *                                         it solves, and how it fits into a larger system architecture.
     * @param null|string      $termsOfService Optional URL to the service's terms of service, acceptable use
     *                                         policy, or service level agreement. Specifies legal terms and
     *                                         usage constraints that clients must accept when using the service.
     * @param null|ContactData $contact        Optional contact information for the service maintainers or support
     *                                         team. Provides developers with a point of contact for questions,
     *                                         bug reports, or feature requests related to the service.
     * @param null|LicenseData $license        Optional licensing information for the service API. Specifies the
     *                                         legal terms under which the API can be used and integrated, including
     *                                         commercial use restrictions and attribution requirements.
     */
    public function __construct(
        string $title,
        string $version,
        public readonly ?string $description = null,
        public readonly ?string $termsOfService = null,
        public readonly ?ContactData $contact = null,
        public readonly ?LicenseData $license = null,
    ) {
        // Validate title
        $trimmedTitle = mb_trim($title);

        if ($trimmedTitle === '') {
            throw EmptyFieldException::forField('title');
        }

        if (mb_strlen($trimmedTitle) > 200) {
            throw FieldExceedsMaxLengthException::forField('title', 200);
        }

        $this->title = $trimmedTitle;

        // Validate semantic version
        $semverPattern = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)'.
            '(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)'.
            '(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?'.
            '(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/';

        if (!preg_match($semverPattern, $version)) {
            throw InvalidSemanticVersionException::forVersion($version);
        }

        $this->version = $version;

        // Validate termsOfService URL if provided
        if ($termsOfService !== null) {
            $this->validateUrl($termsOfService);
        }

        // Validate description length if provided
        if ($description !== null && mb_strlen($description) > 5_000) {
            throw FieldExceedsMaxLengthException::forField('description', 5_000);
        }
    }

    /**
     * Validate URL format.
     *
     * @throws InvalidProtocolException
     * @throws InvalidUrlException
     */
    private function validateUrl(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw InvalidUrlException::invalidFormat('termsOfService');
        }

        $parsed = parse_url($url);

        if (!isset($parsed['scheme']) || !in_array(mb_strtolower($parsed['scheme']), ['http', 'https'], true)) {
            throw InvalidProtocolException::forUrl('termsOfService');
        }
    }
}

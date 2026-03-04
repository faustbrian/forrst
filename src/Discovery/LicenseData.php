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
use Cline\Forrst\Exceptions\InvalidUrlException;
use Spatie\LaravelData\Data;

use const FILTER_VALIDATE_URL;

use function filter_var;
use function in_array;
use function mb_strlen;
use function mb_strtolower;
use function mb_trim;
use function parse_url;

/**
 * License information for the service.
 *
 * Specifies the legal terms under which the API can be used and integrated.
 * Displayed in documentation and API explorers to inform developers about
 * usage rights, restrictions, and attribution requirements.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/system-functions
 * @see https://docs.cline.sh/specs/forrst/discovery#license-object
 */
final class LicenseData extends Data
{
    /**
     * Create a new license information object.
     *
     * @param string      $name License name or identifier (e.g., "MIT", "Apache 2.0", "Proprietary").
     *                          Can be an SPDX license identifier for standard open-source licenses
     *                          or a custom name for proprietary licensing terms. Displayed in API
     *                          documentation to quickly convey licensing model.
     * @param null|string $url  Optional URL to the full license text or licensing agreement.
     *                          Should point to a stable, versioned document containing complete
     *                          legal terms and conditions. Enables developers to review detailed
     *                          licensing requirements before integration.
     *
     * @throws EmptyFieldException            If name is empty
     * @throws FieldExceedsMaxLengthException If name exceeds 100 characters
     * @throws InvalidProtocolException       If URL is not http/https
     * @throws InvalidUrlException            If URL is provided but invalid
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $url = null,
    ) {
        // Validate name
        $trimmedName = mb_trim($name);

        if ($trimmedName === '') {
            throw EmptyFieldException::forField('name');
        }

        if (mb_strlen($trimmedName) > 100) {
            throw FieldExceedsMaxLengthException::forField('name', 100);
        }

        // Validate URL if provided
        if ($this->url !== null && !filter_var($this->url, FILTER_VALIDATE_URL)) {
            throw InvalidUrlException::invalidFormat('url');
        }

        if ($this->url === null) {
            return;
        }

        $parsed = parse_url($this->url);

        if (!isset($parsed['scheme']) || !in_array(mb_strtolower($parsed['scheme']), ['http', 'https'], true)) {
            throw InvalidProtocolException::forUrl('url');
        }
    }
}

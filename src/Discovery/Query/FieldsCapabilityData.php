<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Discovery\Query;

use Spatie\LaravelData\Data;

/**
 * Sparse fieldset capability configuration.
 *
 * Defines whether and how a function supports sparse fieldsets, allowing clients
 * to request only specific fields in the response. Part of the Forrst Query Extension
 * specification enabling bandwidth optimization and client-controlled data shaping.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/query
 * @see https://docs.cline.sh/specs/forrst/discovery#fields-capability
 */
final class FieldsCapabilityData extends Data
{
    /**
     * Create a new sparse fieldset capability configuration.
     *
     * @param bool                                   $enabled       Whether sparse fieldsets are supported for this function. When true,
     *                                                              clients can use the fields parameter to request specific attributes
     *                                                              in the response, reducing payload size and improving performance by
     *                                                              excluding unnecessary data from serialization and transmission.
     * @param null|array<string, array<int, string>> $defaultFields Default field selections per resource
     *                                                              type when no explicit fields parameter
     *                                                              is provided. Keys are resource type
     *                                                              names, values are arrays of field names
     *                                                              to include. Defines the baseline field
     *                                                              set returned in standard responses.
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly ?array $defaultFields = null,
    ) {}
}

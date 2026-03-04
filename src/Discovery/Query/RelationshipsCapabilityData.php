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
 * Relationship inclusion capability configuration for query operations.
 *
 * Defines whether and how clients can include related resources in responses,
 * which relationships are available for inclusion, and constraints on nested
 * relationship traversal depth to prevent excessive data fetching.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/query Query extension documentation
 * @see https://docs.cline.sh/specs/forrst/discovery#relationships-capability Relationships capability specification
 */
final class RelationshipsCapabilityData extends Data
{
    /**
     * Create a new relationships capability configuration.
     *
     * @param bool                    $enabled   Whether relationship inclusion is enabled for this endpoint. When false,
     *                                           include query parameters will be rejected or ignored, preventing clients
     *                                           from loading related resources in a single request.
     * @param null|array<int, string> $available List of relationship names that can be included in responses. When null,
     *                                           all relationships defined on the resource can be included. Use this to
     *                                           restrict which relationships are available for performance or security.
     * @param null|int                $maxDepth  Maximum depth of nested relationship traversal allowed. For example, a
     *                                           value of 2 permits "author.posts" but not "author.posts.comments". Prevents
     *                                           excessive database queries and response sizes from deeply nested includes.
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly ?array $available = null,
        public readonly ?int $maxDepth = null,
    ) {}
}

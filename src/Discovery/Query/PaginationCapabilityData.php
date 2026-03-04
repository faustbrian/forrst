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
 * Pagination capability configuration for query operations.
 *
 * Defines the pagination strategies supported by an endpoint, default pagination
 * behavior, and limits on result set sizes. Supports offset-based, cursor-based,
 * and keyset-based pagination strategies.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/query Query extension documentation
 * @see https://docs.cline.sh/specs/forrst/discovery#pagination-capability Pagination capability specification
 */
final class PaginationCapabilityData extends Data
{
    /**
     * Create a new pagination capability configuration.
     *
     * @param array<int, string> $strategies   List of supported pagination strategies. Common values include "offset"
     *                                         (traditional page/offset pagination), "cursor" (cursor-based for
     *                                         real-time data), and "keyset" (ordered set pagination). Clients must
     *                                         use one of these strategies when requesting paginated results.
     * @param null|string        $defaultStyle The pagination style used when the client does not specify one.
     *                                         Must be one of the values in the $strategies array. Determines the
     *                                         default pagination behavior for backward compatibility.
     * @param null|int           $defaultLimit The default number of items returned per page when the client does
     *                                         not specify a limit. Balances response size with API performance
     *                                         and user experience. Should be reasonable for typical use cases.
     * @param null|int           $maxLimit     Maximum number of items that can be requested in a single page.
     *                                         Enforces an upper bound to prevent performance degradation from
     *                                         excessively large result sets. Clients requesting more than this
     *                                         value will receive an error or have the limit capped.
     */
    public function __construct(
        public readonly array $strategies,
        public readonly ?string $defaultStyle = null,
        public readonly ?int $defaultLimit = null,
        public readonly ?int $maxLimit = null,
    ) {}
}

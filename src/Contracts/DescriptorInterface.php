<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Contracts;

use Cline\Forrst\Discovery\FunctionDescriptor;

/**
 * Contract for function descriptor classes.
 *
 * Descriptor classes define the discovery metadata for Forrst functions,
 * separating schema definitions from business logic. Each function class
 * references its descriptor via the #[Descriptor] attribute.
 *
 * Design Decision: The create() method is intentionally static because
 * descriptors represent pure, stateless metadata. Implementations should
 * be idempotent and side-effect free.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @example
 * ```php
 * final class UserListDescriptor implements DescriptorInterface
 * {
 *     public static function create(): FunctionDescriptor
 *     {
 *         return FunctionDescriptor::make()
 *             ->name('users:list')
 *             ->version('1.0.0')
 *             ->summary('Retrieve a paginated list of users')
 *             ->argument(ArgumentData::make('limit')->type('integer')->optional())
 *             ->result(ResultDescriptorData::make()->type('array'));
 *     }
 * }
 * ```
 *
 * @see https://docs.cline.sh/forrst/extensions/discovery
 * @see Descriptor
 * @see FunctionDescriptor
 */
interface DescriptorInterface
{
    /**
     * Create the function descriptor with all discovery metadata.
     *
     * @return FunctionDescriptor Fluent builder containing function schema
     */
    public static function create(): FunctionDescriptor;
}

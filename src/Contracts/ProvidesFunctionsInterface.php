<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Contracts;

/**
 * Contract for extensions that provide functions.
 *
 * Extensions implementing this interface declare functions that should be
 * automatically registered when the extension is enabled on a server.
 * These functions are extension-specific and only available when the
 * parent extension is registered.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/async
 */
interface ProvidesFunctionsInterface
{
    /**
     * Get the function classes provided by this extension.
     *
     * Returns an array of fully qualified class names implementing
     * FunctionInterface. These functions will be registered automatically
     * when the extension is registered on a server.
     *
     * @return array<int, class-string<FunctionInterface>> Function class names
     */
    public function functions(): array;
}

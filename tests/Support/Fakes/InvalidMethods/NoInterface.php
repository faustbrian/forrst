<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fakes\InvalidMethods;

/**
 * Valid class that does NOT implement FunctionInterface.
 * Used to test interface validation in ConfigurationServer.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class NoInterface
{
    public function someMethod(): void
    {
        // This class exists but doesn't implement FunctionInterface
    }
}

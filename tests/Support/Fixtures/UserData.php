<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fixtures;

use Spatie\LaravelData\Data;

/**
 * Test fixture for AbstractDataResource tests.
 * Tests type derivation: UserData -> user
 *
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class UserData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
    ) {}
}

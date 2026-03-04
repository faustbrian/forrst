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
 * Tests type derivation with compound name: CustomEntityData -> CustomEntity
 *
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class CustomEntityData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly array $metadata,
    ) {}
}

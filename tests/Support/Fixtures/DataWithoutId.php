<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fixtures;

use Cline\Forrst\Data\AbstractData;

/**
 * Test fixture for AbstractDataResource tests.
 * Tests getId() when Data object has no id property.
 *
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final readonly class DataWithoutId extends AbstractData
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
    ) {}
}

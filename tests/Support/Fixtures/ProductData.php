<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fixtures;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

/**
 * Test fixture for Data object parameter testing.
 * Simple product data structure with basic validation.
 *
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class ProductData extends Data
{
    public function __construct(
        #[Required()]
        public readonly string $title,
        #[Required(), Min(0)]
        public readonly float $price,
        public readonly ?string $description = null,
    ) {}
}

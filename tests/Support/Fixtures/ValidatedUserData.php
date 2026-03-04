<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fixtures;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

/**
 * Test fixture for Data object parameter validation in CallMethod tests.
 * Contains validation rules to test validateAndCreate behavior.
 *
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class ValidatedUserData extends Data
{
    public function __construct(
        #[Required(), Max(100), Min(3)]
        public readonly string $name,
        #[Required(), Email()]
        public readonly string $email,
        #[ Max(150), Min(1)]
        public readonly ?int $age = null,
    ) {}
}

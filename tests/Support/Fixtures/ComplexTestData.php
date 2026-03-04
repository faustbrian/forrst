<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fixtures;

use Cline\Forrst\Data\AbstractData;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;

/**
 * Complex test data fixture with multiple fields for RulesTransformer tests.
 *
 * This fixture demonstrates advanced Spatie Laravel Data usage with multiple
 * field types, validation constraints, and nullable fields. Tests complex
 * validation attribute combinations and their JSON Schema transformations.
 *
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class ComplexTestData extends AbstractData
{
    /**
     * Create a new complex test data instance.
     *
     * Demonstrates advanced Spatie Data validation patterns:
     * - Required fields with constraints (name, email)
     * - Laravel email validation format
     * - Nullable fields with default values (age, bio)
     * - Min/Max constraints for length and value ranges
     * - Mixed type constraints (string, integer)
     *
     * @param string      $name  Required string field with length constraints
     * @param string      $email Required email field with Laravel validation
     * @param null|int    $age   Optional integer field with value range
     * @param null|string $bio   Optional text field with max length
     */
    public function __construct(
        #[Required(), Max(255), Min(2), StringType()]
        public readonly string $name,
        #[Required(), Email(), Max(255)]
        public readonly string $email,
        #[Nullable(), IntegerType(), Max(150), Min(0)]
        public readonly ?int $age = null,
        #[Nullable(), Max(1_000), StringType()]
        public readonly ?string $bio = null,
    ) {}
}

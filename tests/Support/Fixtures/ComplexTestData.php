<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fixtures;

use Cline\Forrst\Data\AbstractData;
use Cline\Struct\Attributes\Validate;

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
final readonly class ComplexTestData extends AbstractData
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
        #[Validate('required|max:255|min:2|string')]
        public readonly string $name,
        #[Validate('required|email|max:255')]
        public readonly string $email,
        #[Validate('nullable|integer|max:150|min:0')]
        public readonly ?int $age = null,
        #[Validate('nullable|max:1000|string')]
        public readonly ?string $bio = null,
    ) {}
}

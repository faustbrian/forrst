<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fixtures;

use Cline\Forrst\Data\AbstractData;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;

/**
 * Nested test data fixture with object relationships for RulesTransformer tests.
 *
 * This fixture demonstrates Spatie Laravel Data nested object relationships.
 * Tests how nested Data objects and array fields transform to JSON Schema,
 * including proper handling of composition patterns.
 *
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class NestedTestData extends AbstractData
{
    /**
     * Create a new nested test data instance.
     *
     * Demonstrates Spatie Data nested composition patterns:
     * - Simple required fields (title)
     * - Nested Data object relationships (author)
     * - Nullable array fields (tags)
     * - Mixed validation constraints
     *
     * This tests how RulesTransformer handles complex object graphs
     * and transforms them to proper JSON Schema definitions.
     *
     * @param string         $title  Required string field with minimum length
     * @param SimpleTestData $author Required nested Data object (composition)
     * @param null|array     $tags   Optional array field for flexible metadata
     */
    public function __construct(
        #[Required(), Min(1), StringType()]
        public readonly string $title,
        #[Required()]
        public readonly SimpleTestData $author,
        #[Nullable(), ArrayType()]
        public readonly ?array $tags = null,
    ) {}
}

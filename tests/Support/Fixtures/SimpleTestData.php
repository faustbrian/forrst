<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fixtures;

use Cline\Forrst\Data\AbstractData;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;

/**
 * Simple test data fixture for RulesTransformer tests.
 *
 * This fixture demonstrates basic Spatie Laravel Data usage with a single
 * required field. Used to test how validation attributes transform to JSON Schema.
 *
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class SimpleTestData extends AbstractData
{
    /**
     * Create a new simple test data instance.
     *
     * Demonstrates Spatie Data validation attributes:
     * - Required: Field must be present
     * - StringType: Must be a string value
     *
     * @param string $name Required string field for basic testing
     */
    public function __construct(
        #[Required(), StringType()]
        public readonly string $name,
    ) {}
}

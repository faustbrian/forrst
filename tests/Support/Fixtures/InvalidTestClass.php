<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fixtures;

/**
 * Invalid test class that does NOT extend Data - for testing error handling.
 *
 * This fixture intentionally does NOT extend Spatie Laravel Data AbstractData.
 * Used to verify RulesTransformer properly handles and rejects invalid classes
 * that don't follow the expected Data object pattern.
 *
 * Tests the "Sad Path" scenario where transformDataObject receives a class
 * that isn't a valid Spatie Data object.
 *
 * @author Brian Faust <brian@cline.sh>
 * @internal
 *
 * @psalm-immutable
 */
final readonly class InvalidTestClass
{
    /**
     * Create a new invalid test class instance.
     *
     * This is a plain PHP class without any Spatie Data inheritance
     * or validation attributes, used to test error handling.
     *
     * @param string $value Test value field
     */
    public function __construct(
        public string $value,
    ) {}
}

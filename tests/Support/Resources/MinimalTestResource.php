<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Resources;

use Cline\Forrst\Resources\AbstractResource;
use Override;

/**
 * Minimal concrete implementation of AbstractResource for testing.
 *
 * This class provides the minimum required implementation to test
 * the default behavior of AbstractResource's static methods.
 * It does NOT override any of the static methods, allowing us to test
 * that they return empty arrays by default.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MinimalTestResource extends AbstractResource
{
    #[Override()]
    public function getType(): string
    {
        return 'test';
    }

    #[Override()]
    public function getId(): string
    {
        return '1';
    }

    #[Override()]
    public function getAttributes(): array
    {
        return ['name' => 'Test Resource'];
    }

    #[Override()]
    public function getRelations(): array
    {
        return [];
    }
}

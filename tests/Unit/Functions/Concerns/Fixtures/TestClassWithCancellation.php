<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Functions\Concerns\Fixtures;

use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Functions\Concerns\InteractsWithCancellation;

/**
 * Test class using the InteractsWithCancellation trait.
 *
 * Note: Cannot be readonly since InteractsWithCancellation trait
 * has a mutable $cachedCancellationToken property.
 * @author Brian Faust <brian@cline.sh>
 */
final class TestClassWithCancellation
{
    use InteractsWithCancellation;

    public function __construct(
        public readonly RequestObjectData $requestObject,
    ) {}

    public function exposeGetCancellationToken(): ?string
    {
        return $this->getCancellationToken();
    }

    public function exposeIsCancellationRequested(): bool
    {
        return $this->isCancellationRequested();
    }

    public function exposeThrowIfCancellationRequested(): void
    {
        $this->throwIfCancellationRequested();
    }
}

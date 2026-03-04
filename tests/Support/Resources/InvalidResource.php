<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Resources;

/**
 * Invalid resource class that doesn't implement ResourceInterface.
 * Used for testing error handling in ResourceRepository.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class InvalidResource
{
    /**
     * Constructor accepts a model but doesn't implement ResourceInterface.
     */
    public function __construct()
    {
        // Intentionally empty - this class doesn't implement ResourceInterface
    }
}

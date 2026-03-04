<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions\AtomicLock\Functions;

use Cline\Forrst\Attributes\Descriptor;
use Cline\Forrst\Exceptions\LockKeyRequiredException;
use Cline\Forrst\Extensions\AtomicLock\AtomicLockExtension;
use Cline\Forrst\Extensions\AtomicLock\Descriptors\LockStatusDescriptor;
use Cline\Forrst\Functions\AbstractFunction;

use function is_string;

/**
 * Lock status function.
 *
 * Implements forrst.locks.status for checking the status of an atomic lock.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/atomic-lock
 */
#[Descriptor(LockStatusDescriptor::class)]
final class LockStatusFunction extends AbstractFunction
{
    /**
     * Create a new lock status function instance.
     *
     * @param AtomicLockExtension $extension Atomic lock extension instance
     */
    public function __construct(
        private readonly AtomicLockExtension $extension,
    ) {}

    /**
     * Execute the lock status function.
     *
     * @throws LockKeyRequiredException If key is missing
     *
     * @return array<string, mixed> Lock status information
     */
    public function __invoke(): array
    {
        $key = $this->requestObject->getArgument('key');

        if (!is_string($key) || $key === '') {
            throw LockKeyRequiredException::create();
        }

        return $this->extension->getLockStatus($key);
    }
}

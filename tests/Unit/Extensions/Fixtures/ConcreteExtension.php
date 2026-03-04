<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Extensions\Fixtures;

use Cline\Forrst\Extensions\AbstractExtension;

/**
 * Concrete test implementation of AbstractExtension.
 *
 * Provides a minimal implementation for testing the base extension functionality
 * without any custom behavior. Only implements the required getUrn() method.
 *
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class ConcreteExtension extends AbstractExtension
{
    /**
     * {@inheritDoc}
     */
    public function getUrn(): string
    {
        return 'urn:test:extension:concrete';
    }
}

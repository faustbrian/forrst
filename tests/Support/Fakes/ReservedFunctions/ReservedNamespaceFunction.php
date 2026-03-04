<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fakes\ReservedFunctions;

use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Functions\AbstractFunction;
use Override;

/**
 * Test function that uses a reserved namespace.
 *
 * This function is used to test that reserved namespace enforcement works correctly.
 * It should throw a ReservedNamespaceException when registered.
 *
 * NOTE: This file is located in ReservedFunctions/ directory (not Functions/) to avoid
 * being auto-discovered by ConfigurationServer, which would fail due to the reserved namespace.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ReservedNamespaceFunction extends AbstractFunction
{
    #[Override()]
    public function getUrn(): string
    {
        return 'urn:forrst:forrst:fn:test';
    }

    public function handle(RequestObjectData $requestObject): string
    {
        return 'test';
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fakes\Functions\Versioned;

use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Functions\AbstractFunction;
use Override;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class CalculatorV3Alpha1 extends AbstractFunction
{
    #[Override()]
    public function getUrn(): string
    {
        return 'urn:app:forrst:fn:math:calculator';
    }

    #[Override()]
    public function getVersion(): string
    {
        return '3.0.0-alpha.1';
    }

    public function handle(RequestObjectData $requestObject): string
    {
        return 'v3-alpha1';
    }
}

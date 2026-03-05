<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

use Facade\IgnitionContracts\BaseSolution;
use Facade\IgnitionContracts\ProvidesSolution;
use Facade\IgnitionContracts\Solution;

/**
 * Base exception for async operation-related errors in Forrst RPC.
 *
 * Provides a common foundation for operation-specific exceptions such as
 * cancellation failures, operation failures, and not found errors. This
 * abstract class should be extended by concrete exception types that
 * implement specific factory methods for their error scenarios.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/extensions/async
 * @see https://docs.cline.sh/forrst/errors
 */
abstract class OperationException extends AbstractRequestException implements ProvidesSolution
{
    // Abstract base - concrete subclasses implement factory methods

    public function getSolution(): Solution
    {
        /** @var BaseSolution $solution */
        $solution = BaseSolution::create('Review package usage and configuration.');

        return $solution
            ->setSolutionDescription('Exception: '.$this->getMessage())
            ->setDocumentationLinks([
                'Package documentation' => 'https://github.com/cline/forrst',
            ]);
    }
}

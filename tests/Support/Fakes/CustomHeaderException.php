<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fakes;

use Cline\Forrst\Exceptions\AbstractRequestException;
use Override;

/**
 * Fake exception class for testing custom headers in exception responses.
 *
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class CustomHeaderException extends AbstractRequestException
{
    #[Override()]
    public function getHeaders(): array
    {
        return ['X-Custom-Header' => 'CustomValue'];
    }
}

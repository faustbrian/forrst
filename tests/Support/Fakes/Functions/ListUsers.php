<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fakes\Functions;

use Cline\Forrst\Functions\AbstractListFunction;
use Override;
use Tests\Support\Resources\UserResource;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class ListUsers extends AbstractListFunction
{
    #[Override()]
    public function getUrn(): string
    {
        return 'urn:app:forrst:fn:users:list';
    }

    #[Override()]
    protected function getResourceClass(): string
    {
        return UserResource::class;
    }
}

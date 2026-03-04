<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fakes;

use Cline\Forrst\Extensions\StreamExtension;
use Cline\Forrst\Servers\AbstractServer;
use Override;
use Tests\Support\Fakes\Functions\GetCollectionData;
use Tests\Support\Fakes\Functions\GetData;
use Tests\Support\Fakes\Functions\GetSpatieData;
use Tests\Support\Fakes\Functions\NotifyHello;
use Tests\Support\Fakes\Functions\NotifySum;
use Tests\Support\Fakes\Functions\RequiresAuthentication;
use Tests\Support\Fakes\Functions\RequiresAuthorization;
use Tests\Support\Fakes\Functions\StreamingSum;
use Tests\Support\Fakes\Functions\Subtract;
use Tests\Support\Fakes\Functions\SubtractWithBinding;
use Tests\Support\Fakes\Functions\Sum;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class Server extends AbstractServer
{
    #[Override()]
    public function getRoutePath(): string
    {
        return '/rpc';
    }

    #[Override()]
    public function getRouteName(): string
    {
        return 'rpc';
    }

    #[Override()]
    public function functions(): array
    {
        return [
            GetCollectionData::class,
            GetData::class,
            GetSpatieData::class,
            NotifyHello::class,
            NotifySum::class,
            RequiresAuthentication::class,
            RequiresAuthorization::class,
            StreamingSum::class,
            Subtract::class,
            SubtractWithBinding::class,
            Sum::class,
        ];
    }

    #[Override()]
    public function extensions(): array
    {
        return [
            new StreamExtension(),
        ];
    }

    #[Override()]
    public function validate(): void
    {
        // No validation needed for test fake
    }
}

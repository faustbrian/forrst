<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Resources;

use Cline\Forrst\Resources\AbstractModelResource;
use Override;
use Tests\Support\Models\Post;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class PostResource extends AbstractModelResource
{
    #[Override()]
    public static function getModel(): string
    {
        return Post::class;
    }

    #[Override()]
    public static function getFields(): array
    {
        return [
            'self' => ['id', 'name'],
            'user' => ['id', 'name'],
        ];
    }

    #[Override()]
    public static function getFilters(): array
    {
        return [
            'self' => ['id', 'name'],
            'user' => ['id', 'name'],
        ];
    }

    #[Override()]
    public static function getRelationships(): array
    {
        return [
            'self' => ['user'],
        ];
    }

    #[Override()]
    public static function getSorts(): array
    {
        return [
            'self' => ['created_at'],
        ];
    }
}

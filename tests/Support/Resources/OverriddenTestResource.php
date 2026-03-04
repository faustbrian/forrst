<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Resources;

use Cline\Forrst\Resources\AbstractResource;
use Override;

/**
 * Test resource with overridden static methods.
 *
 * This class overrides all the static methods from AbstractResource
 * to test that custom implementations work correctly and that the
 * default empty array returns can be replaced with actual data.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class OverriddenTestResource extends AbstractResource
{
    #[Override()]
    public static function getFields(): array
    {
        return [
            'self' => ['id', 'name', 'email'],
            'posts' => ['id', 'title'],
        ];
    }

    #[Override()]
    public static function getFilters(): array
    {
        return [
            'self' => ['name', 'email', 'created_at'],
            'posts' => ['title', 'status'],
        ];
    }

    #[Override()]
    public static function getRelationships(): array
    {
        return [
            'self' => ['posts', 'comments', 'profile'],
        ];
    }

    #[Override()]
    public static function getSorts(): array
    {
        return [
            'self' => ['name', 'created_at', 'updated_at'],
            'posts' => ['published_at'],
        ];
    }

    #[Override()]
    public function getType(): string
    {
        return 'overridden';
    }

    #[Override()]
    public function getId(): string
    {
        return '42';
    }

    #[Override()]
    public function getAttributes(): array
    {
        return [
            'name' => 'Overridden Resource',
            'email' => 'test@example.com',
        ];
    }

    #[Override()]
    public function getRelations(): array
    {
        return [];
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Resources;

use Cline\Forrst\Resources\AbstractModelResource;
use Illuminate\Support\Str;
use Override;

use function sprintf;

/**
 * Test resource for edge case testing with dynamic class name simulation.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class EdgeCaseResource extends AbstractModelResource
{
    private static string $testClassName = 'EdgeCaseResource';

    public static function setTestClassName(string $name): void
    {
        self::$testClassName = $name;
    }

    #[Override()]
    public static function getModel(): string
    {
        return sprintf(
            'App\\Models\\%s',
            Str::beforeLast(self::$testClassName, 'Resource'),
        );
    }

    #[Override()]
    public static function getFields(): array
    {
        return ['self' => []];
    }

    #[Override()]
    public static function getFilters(): array
    {
        return ['self' => []];
    }

    #[Override()]
    public static function getRelationships(): array
    {
        return ['self' => []];
    }

    #[Override()]
    public static function getSorts(): array
    {
        return ['self' => []];
    }
}

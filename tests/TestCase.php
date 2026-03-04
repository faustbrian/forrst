<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Forrst\ServiceProvider;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Override;
use Spatie\LaravelData\LaravelDataServiceProvider;

use function realpath;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * {@inheritDoc}
     */
    #[Override()]
    protected function getEnvironmentSetUp($app): void
    {
        $app->config->set('app.key', 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA');
        $app->config->set('rpc.servers', []);

        $app->config->set('cache.driver', 'array');

        $app->config->set('database.default', 'sqlite');
        $app->config->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app->config->set('mail.driver', 'log');

        $app->config->set('session.driver', 'array');

        $app->useStoragePath(realpath(__DIR__.'/storage'));

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('tokens', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->nullableUlidMorphs('owner');
            $table->string('type');
            $table->string('mode');
            $table->string('name');
            $table->longText('token');
            $table->string('hash');
            $table->json('scopes');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('forrst_operations', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('function');
            $table->string('version')->nullable();
            $table->string('status')->default('pending')->index();
            $table->decimal('progress', 5, 4)->nullable();
            $table->json('result')->nullable();
            $table->json('errors')->nullable();
            $table->json('metadata')->nullable();
            $table->string('caller_id')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['caller_id', 'status']);
            $table->index(['function', 'status']);
        });
    }

    /**
     * Get package providers.
     *
     * @param  Application                                                   $app
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    #[Override()]
    protected function getPackageProviders($app)
    {
        return [
            BusServiceProvider::class,
            LaravelDataServiceProvider::class,
            ServiceProvider::class,
        ];
    }
}

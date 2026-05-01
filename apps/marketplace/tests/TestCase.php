<?php

declare(strict_types=1);

namespace App\Tests;

use App\Http\Controllers\BrowseController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PluginCompareController;
use App\Http\Controllers\PluginDetailController;
use App\Http\Controllers\PublisherProfileController;
use App\Tests\Fixtures\TestUser;
use Arqel\Core\ArqelServiceProvider;
use Arqel\Marketplace\MarketplaceServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        Inertia::setRootView('test-app');

        if (! Schema::hasTable('marketplace_test_users')) {
            Schema::create('marketplace_test_users', static function (Blueprint $table): void {
                $table->id();
                $table->string('name')->default('user');
                $table->timestamps();
            });
        }
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ArqelServiceProvider::class,
            MarketplaceServiceProvider::class,
            InertiaServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        /** @var Application $app */
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('inertia.testing.ensure_pages_exist', false);
        $app['config']->set('inertia.testing.page_paths', []);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => TestUser::class,
        ]);
        $app['view']->addLocation(__DIR__.'/Fixtures/views');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../../packages/marketplace/database/migrations');
    }

    protected function defineRoutes($router): void
    {
        Route::get('/', LandingController::class)->name('landing');
        Route::get('/browse', BrowseController::class)->name('browse');
        Route::get('/compare', PluginCompareController::class)->name('compare');
        Route::get('/plugins/{slug}', PluginDetailController::class)->name('plugin.detail');
        Route::get('/publishers/{slug}', PublisherProfileController::class)->name('publisher.profile');

        Route::middleware(['auth'])->group(static function (): void {
            Route::get('/checkout/{slug}', [CheckoutController::class, 'start'])->name('checkout.start');
            Route::post('/checkout/{slug}/initiate', [CheckoutController::class, 'initiate'])->name('checkout.initiate');
            Route::get('/checkout/{slug}/success', [CheckoutController::class, 'success'])->name('checkout.success');
            Route::get('/checkout/{slug}/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');
        });
    }
}

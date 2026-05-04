<?php

declare(strict_types=1);

namespace App\Tests;

use App\Arqel\Panel;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\PostAiSummaryController;
use App\Http\Controllers\PostCreateController;
use App\Http\Controllers\PostListController;
use App\Http\Controllers\PostTransitionController;
use App\Http\Controllers\TagClassifyController;
use App\Http\Controllers\TagListController;
use App\Models\User;
use App\Providers\ArqelServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        Inertia::setRootView('test-app');
        Panel::flush();
        $this->app->register(ArqelServiceProvider::class);
    }

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
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
            'model' => User::class,
        ]);
        $app['view']->addLocation(__DIR__.'/Fixtures/views');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function defineRoutes($router): void
    {
        Route::prefix('admin')->group(static function (): void {
            Route::get('/', AdminDashboardController::class)->name('admin.dashboard');
            Route::get('/posts', PostListController::class)->name('admin.posts.index');
            Route::get('/posts/create', PostCreateController::class)->name('admin.posts.create');
            Route::post('/posts/ai/summary', PostAiSummaryController::class)->name('admin.posts.ai.summary');
            Route::post('/posts/{post}/transition', PostTransitionController::class)->name('admin.posts.transition');
            Route::get('/tags', TagListController::class)->name('admin.tags.index');
            Route::post('/tags/ai/classify', TagClassifyController::class)->name('admin.tags.ai.classify');
        });
    }
}

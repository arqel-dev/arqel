<?php

declare(strict_types=1);

namespace Arqel\Core\Tests;

use Arqel\Core\ArqelServiceProvider;
use Illuminate\Foundation\Application;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * `inertiajs/inertia-laravel` ships package auto-discovery
     * (`extra.laravel.providers` in its composer.json), but Testbench's
     * minimal application does not run Laravel's package-discovery
     * boot step — so `Inertia\ServiceProvider` (and its `Ssr\Gateway`
     * binding) never registers unless listed here explicitly. Without
     * it, any full-page `Inertia::render()` hit via a real HTTP request
     * (not just `X-Inertia: true` partial reloads) fails with
     * "Target [Inertia\Ssr\Gateway] is not instantiable."
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            ArqelServiceProvider::class,
        ];
    }

    /**
     * Run feature/integration tests against an in-memory SQLite
     * connection so we never touch the host filesystem and stay
     * isolated between test runs.
     */
    protected function defineEnvironment($app): void
    {
        /** @var Application $app */
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Stable, deterministic app key so HTTP feature tests can boot
        // the encrypter (cookie middleware, session, etc.) without
        // requiring a `.env` file.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // No compiled SSR bundle exists in the test environment — leaving
        // Inertia's SSR gateway enabled (its package default) makes any
        // full-page `Inertia::render()` HTTP test fail with "Target
        // [Inertia\Ssr\Gateway] is not instantiable." Feature tests only
        // care about the HTTP status + props, never the rendered HTML.
        $app['config']->set('inertia.ssr.enabled', false);
    }

    /**
     * Load package migrations and relation-manager fixtures.
     *
     * The provider does NOT set spatie's `runsMigrations(true)` (see
     * `ArqelServiceProvider::configurePackage()`), so its auto-load never
     * fires here — this hook is the single source that creates the
     * package tables (e.g. `notifications`) for the test DB. There is no
     * double-load with the dated migration name registered via
     * `hasMigration()`, matching the pattern used by `arqel-dev/versioning`.
     *
     * Relation-manager fixtures (Task 4+): `rel_posts` (hasMany `comments`,
     * belongsToMany `tags`), `rel_comments`, `rel_tags`, and the `rel_post_tag`
     * pivot. Shared across `Relations/*` feature tests so each test does not
     * have to redeclare the same four tables.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        \Illuminate\Support\Facades\Schema::create('rel_posts', function ($t): void {
            $t->increments('id');
            $t->string('title')->nullable();
        });
        \Illuminate\Support\Facades\Schema::create('rel_comments', function ($t): void {
            $t->increments('id');
            $t->unsignedInteger('post_id');
            $t->string('body')->nullable();
            // Backs the `canSee(fn () => false)`-equivalent redaction test
            // in RelationIndexTest — needs a real column so the pre-fix raw
            // `toArray()` payload actually contains the value to strip
            // (review finding I1).
            $t->string('secret')->nullable();
        });
        \Illuminate\Support\Facades\Schema::create('rel_tags', function ($t): void {
            $t->increments('id');
            $t->string('name')->nullable();
        });
        \Illuminate\Support\Facades\Schema::create('rel_post_tag', function ($t): void {
            $t->unsignedInteger('post_id');
            $t->unsignedInteger('tag_id');
            // Extra pivot columns used to prove the pivotFields() allowlist:
            // `role`/`approved`/`anything` are NEVER declared allowed by any
            // fixture manager (mass-assignment injection targets), while
            // `note` IS declared allowed by NotableTagsRelationManager.
            $t->string('role')->nullable();
            $t->boolean('approved')->nullable();
            $t->string('note')->nullable();
        });
    }
}

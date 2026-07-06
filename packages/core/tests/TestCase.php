<?php

declare(strict_types=1);

namespace Arqel\Core\Tests;

use Arqel\Core\ArqelServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
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
    }

    /**
     * Relation-manager fixtures (Task 4+): `rel_posts` (hasMany `comments`,
     * belongsToMany `tags`), `rel_comments`, `rel_tags`, and the `rel_post_tag`
     * pivot. Shared across `Relations/*` feature tests so each test does not
     * have to redeclare the same four tables.
     */
    protected function defineDatabaseMigrations(): void
    {
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

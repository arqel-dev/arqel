<?php

declare(strict_types=1);

namespace Arqel\Realtime\Tests;

use Arqel\Realtime\RealtimeServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('fake_resource_records')) {
            Schema::create('fake_resource_records', static function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('arqel_yjs_documents')) {
            Schema::create('arqel_yjs_documents', static function (Blueprint $table): void {
                $table->id();
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->string('field');
                $table->binary('state')->nullable();
                $table->unsignedInteger('version')->default(0);
                $table->unsignedBigInteger('last_user_id')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['model_type', 'model_id', 'field'], 'arqel_yjs_documents_unique');
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', static function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
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
            RealtimeServiceProvider::class,
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
        $app['config']->set('broadcasting.default', 'null');
    }
}

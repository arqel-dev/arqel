<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arqel_plugins', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description');
            $table->enum('type', ['field', 'widget', 'integration', 'theme']);
            $table->unsignedBigInteger('author_id')->nullable()->index();
            $table->string('composer_package')->nullable();
            $table->string('npm_package')->nullable();
            $table->string('github_url');
            $table->string('license')->default('MIT');
            $table->json('screenshots')->nullable();
            $table->string('latest_version')->nullable();
            $table->enum('status', ['draft', 'pending', 'published', 'archived'])->default('draft');
            $table->timestamps();

            $table->index(['type', 'status']);
        });

        Schema::create('arqel_plugin_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plugin_id')
                ->constrained('arqel_plugins')
                ->cascadeOnDelete();
            $table->string('version');
            $table->text('changelog')->nullable();
            $table->timestamp('released_at');
            $table->timestamps();

            $table->unique(['plugin_id', 'version']);
        });

        Schema::create('arqel_plugin_installations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plugin_id')
                ->constrained('arqel_plugins')
                ->cascadeOnDelete();
            $table->foreignId('plugin_version_id')
                ->nullable()
                ->constrained('arqel_plugin_versions')
                ->nullOnDelete();
            $table->timestamp('installed_at');
            $table->string('anonymized_user_hash')->nullable();
            $table->json('context')->nullable();

            $table->index(['plugin_id', 'installed_at']);
        });

        Schema::create('arqel_plugin_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plugin_id')
                ->constrained('arqel_plugins')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedTinyInteger('stars');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arqel_plugin_reviews');
        Schema::dropIfExists('arqel_plugin_installations');
        Schema::dropIfExists('arqel_plugin_versions');
        Schema::dropIfExists('arqel_plugins');
    }
};

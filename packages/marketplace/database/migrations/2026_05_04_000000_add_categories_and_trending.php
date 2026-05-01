<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arqel_plugin_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('arqel_plugin_categories')
                ->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('arqel_plugin_category_assignments', function (Blueprint $table): void {
            $table->foreignId('plugin_id')
                ->constrained('arqel_plugins')
                ->cascadeOnDelete();
            $table->foreignId('category_id')
                ->constrained('arqel_plugin_categories')
                ->cascadeOnDelete();

            $table->primary(['plugin_id', 'category_id']);
        });

        Schema::table('arqel_plugins', function (Blueprint $table): void {
            $table->boolean('featured')->default(false)->after('status');
            $table->timestamp('featured_at')->nullable()->after('featured');
            $table->float('trending_score')->default(0)->after('featured_at');
            $table->timestamp('trending_score_updated_at')->nullable()->after('trending_score');
        });

        $now = now();
        $defaults = [
            ['slug' => 'fields', 'name' => 'Fields', 'sort_order' => 1],
            ['slug' => 'widgets', 'name' => 'Widgets', 'sort_order' => 2],
            ['slug' => 'themes', 'name' => 'Themes', 'sort_order' => 3],
            ['slug' => 'integrations', 'name' => 'Integrations', 'sort_order' => 4],
            ['slug' => 'utilities', 'name' => 'Utilities', 'sort_order' => 5],
        ];

        foreach ($defaults as $row) {
            DB::table('arqel_plugin_categories')->updateOrInsert(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'sort_order' => $row['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::table('arqel_plugins', function (Blueprint $table): void {
            $table->dropColumn([
                'featured',
                'featured_at',
                'trending_score',
                'trending_score_updated_at',
            ]);
        });

        Schema::dropIfExists('arqel_plugin_category_assignments');
        Schema::dropIfExists('arqel_plugin_categories');
    }
};

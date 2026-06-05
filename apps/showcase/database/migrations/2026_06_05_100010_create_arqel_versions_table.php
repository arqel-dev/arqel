<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local copy of arqel-dev/versioning's `arqel_versions` migration.
 *
 * The package registers this migration via Spatie package-tools
 * `hasMigration('create_arqel_versions_table')`, which is publish-only
 * (not auto-loaded) and expects a `.stub` source that the package does
 * not ship. We mirror the table here so the `Versionable` trait works.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arqel_versions', function (Blueprint $table): void {
            $table->id();
            $table->morphs('versionable');
            $table->json('payload');
            $table->json('changes')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['versionable_type', 'versionable_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arqel_versions');
    }
};

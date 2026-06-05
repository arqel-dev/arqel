<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local copy of arqel-dev/workflow's `arqel_state_transitions` migration.
 *
 * The package registers this migration via Spatie package-tools
 * `hasMigration('create_arqel_state_transitions_table')`, which is
 * publish-only (not auto-loaded) and expects a `.stub` source that the
 * package does not ship. We mirror the table here so `HasWorkflow`'s
 * transition history persists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arqel_state_transitions', function (Blueprint $table): void {
            $table->id();
            $table->morphs('model');
            $table->string('from_state')->nullable();
            $table->string('to_state');
            $table->unsignedBigInteger('transitioned_by_user_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arqel_state_transitions');
    }
};

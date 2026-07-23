<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local copy of arqel-dev/export's `arqel_exports` migration (#381).
 *
 * The package registers this migration via Spatie package-tools
 * `hasMigration('2026_07_17_000000_create_arqel_exports_table')`, which
 * is publish-only (not auto-loaded). We mirror the table here so the
 * ownership-gated `ExportDownloadController` has the `Export` records
 * that `ExportAction::execute()` writes on every bulk export — without
 * this table the export bulk action 500s (Export::create fails) and no
 * download link is flashed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arqel_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('owner_user_id')->nullable()->index();
            $table->string('format', 16);
            $table->string('path');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arqel_exports');
    }
};

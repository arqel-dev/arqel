<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arqel_plugin_security_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plugin_id')
                ->constrained('arqel_plugins')
                ->cascadeOnDelete();
            $table->timestamp('scan_started_at')->nullable();
            $table->timestamp('scan_completed_at')->nullable();
            $table->string('status')->default('pending');
            $table->json('findings')->nullable();
            $table->string('severity')->nullable();
            $table->string('scanner_version')->default('1.0');
            $table->timestamps();

            $table->index(['plugin_id', 'scan_started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arqel_plugin_security_scans');
    }
};

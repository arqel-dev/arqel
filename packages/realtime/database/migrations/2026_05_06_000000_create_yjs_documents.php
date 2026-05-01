<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('arqel_yjs_documents');
    }
};

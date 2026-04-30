<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

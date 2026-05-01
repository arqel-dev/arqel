<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arqel_plugin_reviews', function (Blueprint $table): void {
            $table->boolean('verified_purchaser')->default(false)->after('comment');
            $table->unsignedInteger('helpful_count')->default(0)->after('verified_purchaser');
            $table->unsignedInteger('unhelpful_count')->default(0)->after('helpful_count');
            $table->enum('status', ['pending', 'published', 'hidden'])
                ->default('pending')
                ->after('unhelpful_count');
            $table->text('moderation_reason')->nullable()->after('status');

            $table->index('status');
        });

        Schema::create('arqel_plugin_review_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')
                ->constrained('arqel_plugin_reviews')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->enum('vote', ['helpful', 'unhelpful']);
            $table->timestamps();

            $table->unique(['review_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arqel_plugin_review_votes');

        Schema::table('arqel_plugin_reviews', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'verified_purchaser',
                'helpful_count',
                'unhelpful_count',
                'status',
                'moderation_reason',
            ]);
        });
    }
};

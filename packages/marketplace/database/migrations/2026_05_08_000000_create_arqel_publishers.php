<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arqel_publishers', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name');
            $table->text('bio')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('website_url')->nullable();
            $table->string('github_url')->nullable();
            $table->string('twitter_handle')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamps();

            $table->index(['verified', 'created_at']);
        });

        Schema::table('arqel_plugins', function (Blueprint $table): void {
            $table->unsignedBigInteger('publisher_id')->nullable()->after('publisher_user_id');
            $table->index('publisher_id');
        });
    }

    public function down(): void
    {
        Schema::table('arqel_plugins', function (Blueprint $table): void {
            $table->dropIndex(['publisher_id']);
            $table->dropColumn('publisher_id');
        });

        Schema::dropIfExists('arqel_publishers');
    }
};

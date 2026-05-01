<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arqel_plugins', function (Blueprint $table): void {
            $table->json('submission_metadata')->nullable()->after('status');
            $table->unsignedBigInteger('submitted_by_user_id')->nullable()->after('submission_metadata');
            $table->timestamp('submitted_at')->nullable()->after('submitted_by_user_id');
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->after('submitted_at');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            $table->text('rejection_reason')->nullable()->after('reviewed_at');

            $table->index('submitted_by_user_id');
            $table->index('reviewed_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('arqel_plugins', function (Blueprint $table): void {
            $table->dropIndex(['submitted_by_user_id']);
            $table->dropIndex(['reviewed_by_user_id']);
            $table->dropColumn([
                'submission_metadata',
                'submitted_by_user_id',
                'submitted_at',
                'reviewed_by_user_id',
                'reviewed_at',
                'rejection_reason',
            ]);
        });
    }
};

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
            $table->string('publisher_stripe_account_id')->nullable()->after('publisher_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('arqel_plugins', function (Blueprint $table): void {
            $table->dropColumn('publisher_stripe_account_id');
        });
    }
};

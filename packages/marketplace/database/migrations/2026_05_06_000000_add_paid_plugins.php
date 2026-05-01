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
            $table->unsignedInteger('price_cents')->default(0)->after('license');
            $table->string('currency', 3)->default('USD')->after('price_cents');
            $table->unsignedBigInteger('publisher_user_id')->nullable()->after('currency');
            $table->unsignedTinyInteger('revenue_share_percent')->default(80)->after('publisher_user_id');

            $table->index('publisher_user_id');
        });

        Schema::create('arqel_plugin_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plugin_id')
                ->constrained('arqel_plugins')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('buyer_user_id')->index();
            $table->string('license_key')->unique();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('payment_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['plugin_id', 'status']);
            $table->index(['buyer_user_id', 'status']);
        });

        Schema::create('arqel_plugin_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plugin_id')
                ->constrained('arqel_plugins')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('publisher_user_id')->index();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending');
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamps();

            $table->index(['publisher_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arqel_plugin_payouts');
        Schema::dropIfExists('arqel_plugin_purchases');

        Schema::table('arqel_plugins', function (Blueprint $table): void {
            $table->dropIndex(['publisher_user_id']);
            $table->dropColumn(['price_cents', 'currency', 'publisher_user_id', 'revenue_share_percent']);
        });
    }
};

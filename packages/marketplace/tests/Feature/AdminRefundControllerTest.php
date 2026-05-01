<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginPurchase;
use Arqel\Marketplace\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;

function refundPlugin(): Plugin
{
    /** @var Plugin $p */
    $p = Plugin::query()->create([
        'slug' => 'refund-plugin',
        'name' => 'rf',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
        'price_cents' => 1000,
    ]);

    return $p;
}

function refundAdmin(): TestUser
{
    /** @var TestUser $u */
    $u = TestUser::query()->create(['name' => 'rf-admin']);

    return $u;
}

function refundUser(): TestUser
{
    /** @var TestUser $u */
    $u = TestUser::query()->create(['name' => 'rf-user']);

    return $u;
}

function refundAllow(): void
{
    Gate::define(
        'marketplace.refund',
        static fn ($user): bool => $user instanceof TestUser && $user->name === 'rf-admin',
    );
}

function makeCompletedPurchase(int $pluginId, int $buyerId): PluginPurchase
{
    /** @var PluginPurchase $purchase */
    $purchase = PluginPurchase::query()->create([
        'plugin_id' => $pluginId,
        'buyer_user_id' => $buyerId,
        'license_key' => 'ARQ-aaaa-bbbb-cccc-dddd',
        'amount_cents' => 1000,
        'currency' => 'USD',
        'status' => 'completed',
        'purchased_at' => now(),
    ]);

    return $purchase;
}

it('refunds a completed purchase', function (): void {
    refundAllow();
    $plugin = refundPlugin();
    $admin = refundAdmin();
    $purchase = makeCompletedPurchase($plugin->id, 99);

    $response = $this->actingAs($admin)
        ->postJson("/api/marketplace/admin/plugins/{$plugin->slug}/refund/{$purchase->id}");

    $response->assertOk();
    $purchase->refresh();
    expect($purchase->status)->toBe('refunded');
    expect($purchase->refunded_at)->not->toBeNull();
});

it('returns 403 without marketplace.refund gate', function (): void {
    refundAllow();
    $plugin = refundPlugin();
    $purchase = makeCompletedPurchase($plugin->id, 99);
    $user = refundUser();

    $this->actingAs($user)
        ->postJson("/api/marketplace/admin/plugins/{$plugin->slug}/refund/{$purchase->id}")
        ->assertStatus(403);
});

it('returns 422 for already-refunded purchase', function (): void {
    refundAllow();
    $plugin = refundPlugin();
    $admin = refundAdmin();
    $purchase = makeCompletedPurchase($plugin->id, 99);
    $purchase->update(['status' => 'refunded', 'refunded_at' => now()]);

    $this->actingAs($admin)
        ->postJson("/api/marketplace/admin/plugins/{$plugin->slug}/refund/{$purchase->id}")
        ->assertStatus(422);
});

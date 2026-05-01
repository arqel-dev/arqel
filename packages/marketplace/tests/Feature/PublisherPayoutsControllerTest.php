<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginPayout;
use Arqel\Marketplace\Tests\Fixtures\TestUser;

function payoutPlugin(): Plugin
{
    /** @var Plugin $p */
    $p = Plugin::query()->create([
        'slug' => 'payout-plugin',
        'name' => 'payout',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
        'price_cents' => 1000,
    ]);

    return $p;
}

function publisher(): TestUser
{
    /** @var TestUser $u */
    $u = TestUser::query()->create(['name' => 'pub']);

    return $u;
}

function makePayout(int $pluginId, int $publisherId, int $amount = 800): void
{
    PluginPayout::query()->create([
        'plugin_id' => $pluginId,
        'publisher_user_id' => $publisherId,
        'amount_cents' => $amount,
        'currency' => 'USD',
        'status' => 'pending',
        'period_start' => now()->subMonth()->toDateString(),
        'period_end' => now()->toDateString(),
    ]);
}

it('lists only payouts of the authenticated publisher', function (): void {
    $plugin = payoutPlugin();
    $pub = publisher();
    /** @var TestUser $other */
    $other = TestUser::query()->create(['name' => 'other']);

    makePayout($plugin->id, (int) $pub->id, 800);
    makePayout($plugin->id, (int) $pub->id, 1200);
    makePayout($plugin->id, (int) $other->id, 500);

    $response = $this->actingAs($pub)
        ->getJson('/api/marketplace/publisher/payouts');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(2);
});

it('returns 401 unauthenticated', function (): void {
    $this->getJson('/api/marketplace/publisher/payouts')
        ->assertStatus(401);
});

it('paginates with per_page', function (): void {
    $plugin = payoutPlugin();
    $pub = publisher();

    for ($i = 0; $i < 5; $i++) {
        makePayout($plugin->id, (int) $pub->id, 100 + $i);
    }

    $response = $this->actingAs($pub)
        ->getJson('/api/marketplace/publisher/payouts?per_page=2');

    $response->assertOk();
    expect($response->json('meta.per_page'))->toBe(2);
    expect($response->json('meta.total'))->toBe(5);
    expect(count($response->json('data')))->toBe(2);
});

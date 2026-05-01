<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginPurchase;
use Arqel\Marketplace\Tests\Fixtures\TestUser;

function dlPlugin(array $overrides = []): Plugin
{
    /** @var Plugin $p */
    $p = Plugin::query()->create(array_merge([
        'slug' => 'dl-plugin',
        'name' => 'dl',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
    ], $overrides));

    return $p;
}

function dlUser(): TestUser
{
    /** @var TestUser $u */
    $u = TestUser::query()->create(['name' => 'dl-user']);

    return $u;
}

it('returns download url when user has completed purchase', function (): void {
    $plugin = dlPlugin(['price_cents' => 1000]);
    $user = dlUser();

    PluginPurchase::query()->create([
        'plugin_id' => $plugin->id,
        'buyer_user_id' => $user->id,
        'license_key' => 'ARQ-aaaa-bbbb-cccc-dddd',
        'amount_cents' => 1000,
        'currency' => 'USD',
        'status' => 'completed',
        'purchased_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->getJson("/api/marketplace/plugins/{$plugin->slug}/download");

    $response->assertOk();
    expect($response->json('download_url'))->toContain('dl-plugin');
});

it('returns download url for free plugin without purchase', function (): void {
    $plugin = dlPlugin(['price_cents' => 0]);
    $user = dlUser();

    $this->actingAs($user)
        ->getJson("/api/marketplace/plugins/{$plugin->slug}/download")
        ->assertOk();
});

it('returns 403 for premium plugin without purchase', function (): void {
    $plugin = dlPlugin(['price_cents' => 1000]);
    $user = dlUser();

    $this->actingAs($user)
        ->getJson("/api/marketplace/plugins/{$plugin->slug}/download")
        ->assertStatus(403);
});

it('returns 404 for non-existent plugin', function (): void {
    $user = dlUser();

    $this->actingAs($user)
        ->getJson('/api/marketplace/plugins/nope/download')
        ->assertStatus(404);
});

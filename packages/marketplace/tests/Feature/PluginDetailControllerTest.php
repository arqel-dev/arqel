<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginReview;
use Arqel\Marketplace\Models\PluginVersion;

function detailPlugin(array $overrides = []): Plugin
{
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create(array_merge([
        'slug' => 'detail-'.uniqid(),
        'name' => 'Detail Plugin',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
    ], $overrides));

    return $plugin;
}

it('returns plugin with versions and reviews', function (): void {
    $plugin = detailPlugin(['slug' => 'happy', 'latest_version' => '1.0.0']);

    PluginVersion::query()->create([
        'plugin_id' => $plugin->id,
        'version' => '1.0.0',
        'changelog' => 'initial',
        'released_at' => now(),
    ]);

    PluginReview::query()->create([
        'plugin_id' => $plugin->id,
        'user_id' => 42,
        'stars' => 5,
        'comment' => 'great',
        'status' => 'published',
    ]);

    $response = $this->getJson('/api/marketplace/plugins/happy');

    $response->assertOk();
    expect($response->json('plugin.slug'))->toBe('happy');
    expect($response->json('versions'))->toHaveCount(1);
    expect($response->json('reviews'))->toHaveCount(1);
    expect($response->json('reviews.0.stars'))->toBe(5);
});

it('returns 404 when plugin does not exist', function (): void {
    $response = $this->getJson('/api/marketplace/plugins/nonexistent');

    $response->assertNotFound();
});

it('returns 404 when plugin is draft', function (): void {
    detailPlugin(['slug' => 'draft-detail', 'status' => 'draft']);

    $response = $this->getJson('/api/marketplace/plugins/draft-detail');

    $response->assertNotFound();
});

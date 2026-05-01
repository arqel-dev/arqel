<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;

function newPlugin(array $overrides = []): Plugin
{
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create(array_merge([
        'slug' => 'np-'.uniqid(),
        'name' => 'Np',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
    ], $overrides));

    return $plugin;
}

it('returns plugins from last 7 days by default', function (): void {
    $recent = newPlugin(['slug' => 'np-recent']);
    $recent->forceFill(['created_at' => now()->subDays(3)])->saveQuietly();

    $old = newPlugin(['slug' => 'np-old']);
    $old->forceFill(['created_at' => now()->subDays(20)])->saveQuietly();

    $response = $this->getJson('/api/marketplace/new');

    $response->assertOk();
    $slugs = array_column($response->json('data'), 'slug');
    expect($slugs)->toContain('np-recent')
        ->and($slugs)->not->toContain('np-old');
    expect($response->json('meta.days'))->toBe(7);
});

it('respects custom ?days=14', function (): void {
    $recent = newPlugin(['slug' => 'np-rec-14']);
    $recent->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

    $response = $this->getJson('/api/marketplace/new?days=14');

    $response->assertOk();
    $slugs = array_column($response->json('data'), 'slug');
    expect($slugs)->toContain('np-rec-14');
    expect($response->json('meta.days'))->toBe(14);
});

it('excludes plugins older than the window', function (): void {
    $tooOld = newPlugin(['slug' => 'np-too-old']);
    $tooOld->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();

    $response = $this->getJson('/api/marketplace/new?days=7');

    $response->assertOk();
    $slugs = array_column($response->json('data'), 'slug');
    expect($slugs)->not->toContain('np-too-old');
});

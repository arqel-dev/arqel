<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;

function trendPlugin(array $overrides = []): Plugin
{
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create(array_merge([
        'slug' => 'tr-'.uniqid(),
        'name' => 'Tr',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
    ], $overrides));

    return $plugin;
}

it('orders by trending_score desc', function (): void {
    trendPlugin(['slug' => 'tr-low', 'trending_score' => 1.0]);
    trendPlugin(['slug' => 'tr-high', 'trending_score' => 99.5]);
    trendPlugin(['slug' => 'tr-mid', 'trending_score' => 50.0]);

    $response = $this->getJson('/api/marketplace/trending');

    $response->assertOk();
    $slugs = array_column($response->json('data'), 'slug');
    expect($slugs[0])->toBe('tr-high');
    expect($slugs[1])->toBe('tr-mid');
    expect($slugs[2])->toBe('tr-low');
});

it('limits to 20 results', function (): void {
    for ($i = 0; $i < 25; $i++) {
        trendPlugin(['slug' => "tr-{$i}", 'trending_score' => (float) $i]);
    }

    $response = $this->getJson('/api/marketplace/trending');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(20);
});

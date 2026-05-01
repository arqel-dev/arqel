<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;

function makePlugin(array $overrides = []): Plugin
{
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create(array_merge([
        'slug' => 'plugin-'.uniqid(),
        'name' => 'Acme Field',
        'description' => 'A test plugin',
        'type' => 'field',
        'github_url' => 'https://github.com/acme/plugin',
        'license' => 'MIT',
        'status' => 'published',
    ], $overrides));

    return $plugin;
}

it('lists only published plugins', function (): void {
    makePlugin(['slug' => 'pub-1', 'status' => 'published']);
    makePlugin(['slug' => 'draft-1', 'status' => 'draft']);
    makePlugin(['slug' => 'pending-1', 'status' => 'pending']);
    makePlugin(['slug' => 'archived-1', 'status' => 'archived']);

    $response = $this->getJson('/api/marketplace/plugins');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['slug'])->toBe('pub-1');
});

it('filters by type', function (): void {
    makePlugin(['slug' => 'a', 'type' => 'field']);
    makePlugin(['slug' => 'b', 'type' => 'widget']);
    makePlugin(['slug' => 'c', 'type' => 'theme']);

    $response = $this->getJson('/api/marketplace/plugins?type=widget');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['slug'])->toBe('b');
});

it('searches name and description', function (): void {
    makePlugin(['slug' => 's1', 'name' => 'Stripe Integration', 'description' => 'Pay']);
    makePlugin(['slug' => 's2', 'name' => 'Calendar Field', 'description' => 'Stripe-like UI']);
    makePlugin(['slug' => 's3', 'name' => 'Random', 'description' => 'Other']);

    $response = $this->getJson('/api/marketplace/plugins?search=Stripe');

    $response->assertOk();
    $slugs = array_column($response->json('data'), 'slug');
    expect($slugs)->toContain('s1')->toContain('s2');
    expect($slugs)->not->toContain('s3');
});

it('paginates with per_page', function (): void {
    for ($i = 0; $i < 5; $i++) {
        makePlugin(['slug' => "page-{$i}", 'name' => "Plugin {$i}"]);
    }

    $response = $this->getJson('/api/marketplace/plugins?per_page=2&page=2');

    $response->assertOk();
    expect($response->json('per_page'))->toBe(2);
    expect($response->json('current_page'))->toBe(2);
    expect($response->json('total'))->toBe(5);
    expect($response->json('data'))->toHaveCount(2);
});

it('returns expected JSON shape', function (): void {
    makePlugin([
        'slug' => 'shape-1',
        'composer_package' => 'acme/x',
        'npm_package' => '@acme/x',
        'screenshots' => ['a.png', 'b.png'],
        'latest_version' => '1.0.0',
    ]);

    $response = $this->getJson('/api/marketplace/plugins');

    $response->assertOk();
    $first = $response->json('data.0');
    expect($first)->toHaveKeys([
        'id', 'slug', 'name', 'description', 'type',
        'composer_package', 'npm_package', 'github_url',
        'license', 'screenshots', 'latest_version',
    ]);
    expect($first['screenshots'])->toBe(['a.png', 'b.png']);
});

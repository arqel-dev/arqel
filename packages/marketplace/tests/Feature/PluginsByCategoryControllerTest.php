<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginCategory;

function categoryPlugin(array $overrides = []): Plugin
{
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create(array_merge([
        'slug' => 'cat-plugin-'.uniqid(),
        'name' => 'Cat Plugin',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
    ], $overrides));

    return $plugin;
}

it('lists plugins assigned to a category', function (): void {
    /** @var PluginCategory $cat */
    $cat = PluginCategory::query()->where('slug', 'fields')->firstOrFail();
    $p1 = categoryPlugin(['slug' => 'plug-in-cat-1', 'name' => 'A first']);
    $p2 = categoryPlugin(['slug' => 'plug-in-cat-2', 'name' => 'B second']);
    $other = categoryPlugin(['slug' => 'plug-not-in-cat', 'name' => 'Other']);

    $cat->plugins()->attach([$p1->id, $p2->id]);

    $response = $this->getJson('/api/marketplace/categories/fields/plugins');

    $response->assertOk();
    $slugs = array_column($response->json('data'), 'slug');
    expect($slugs)->toContain('plug-in-cat-1')
        ->and($slugs)->toContain('plug-in-cat-2')
        ->and($slugs)->not->toContain('plug-not-in-cat');
});

it('paginates plugins in a category', function (): void {
    /** @var PluginCategory $cat */
    $cat = PluginCategory::query()->where('slug', 'widgets')->firstOrFail();
    for ($i = 0; $i < 5; $i++) {
        $p = categoryPlugin(['slug' => "wpag-{$i}", 'name' => "W{$i}"]);
        $cat->plugins()->attach($p->id);
    }

    $response = $this->getJson('/api/marketplace/categories/widgets/plugins?per_page=2&page=2');

    $response->assertOk();
    expect($response->json('meta.current_page'))->toBe(2);
    expect($response->json('meta.total'))->toBe(5);
    expect($response->json('data'))->toHaveCount(2);
});

it('returns 404 for unknown category slug', function (): void {
    $this->getJson('/api/marketplace/categories/does-not-exist/plugins')
        ->assertStatus(404);
});

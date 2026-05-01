<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginReview;

function scopePlugin(array $overrides = []): Plugin
{
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create(array_merge([
        'slug' => 'scope-'.uniqid(),
        'name' => 'Scoped',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
    ], $overrides));

    return $plugin;
}

it('scopePublished filters non-published rows', function (): void {
    scopePlugin(['slug' => 'p1', 'status' => 'published']);
    scopePlugin(['slug' => 'p2', 'status' => 'draft']);
    scopePlugin(['slug' => 'p3', 'status' => 'archived']);

    $slugs = Plugin::query()->published()->pluck('slug')->all();

    expect($slugs)->toBe(['p1']);
});

it('scopeOfType filters by type', function (): void {
    scopePlugin(['slug' => 't1', 'type' => 'field']);
    scopePlugin(['slug' => 't2', 'type' => 'widget']);

    $slugs = Plugin::query()->ofType('widget')->pluck('slug')->all();

    expect($slugs)->toBe(['t2']);
});

it('scopeSearch matches name and description', function (): void {
    scopePlugin(['slug' => 'sn', 'name' => 'NeedleHere', 'description' => 'x']);
    scopePlugin(['slug' => 'sd', 'name' => 'foo', 'description' => 'haystack with NeedleHere inside']);
    scopePlugin(['slug' => 'so', 'name' => 'bar', 'description' => 'unrelated']);

    $slugs = Plugin::query()->search('NeedleHere')->pluck('slug')->all();

    expect($slugs)->toContain('sn')->toContain('sd');
    expect($slugs)->not->toContain('so');
});

it('scopePositive on PluginReview returns >=4 stars', function (): void {
    $plugin = scopePlugin(['slug' => 'rev']);
    PluginReview::query()->create(['plugin_id' => $plugin->id, 'stars' => 5, 'user_id' => 1]);
    PluginReview::query()->create(['plugin_id' => $plugin->id, 'stars' => 4, 'user_id' => 2]);
    PluginReview::query()->create(['plugin_id' => $plugin->id, 'stars' => 3, 'user_id' => 3]);
    PluginReview::query()->create(['plugin_id' => $plugin->id, 'stars' => 1, 'user_id' => 4]);

    expect(PluginReview::query()->positive()->count())->toBe(2);
});

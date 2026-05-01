<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginCategory;

it('scopeRoot filters categories without parent', function (): void {
    /** @var PluginCategory $root */
    $root = PluginCategory::query()->create([
        'slug' => 'sc-root',
        'name' => 'Root',
        'sort_order' => 1,
    ]);
    PluginCategory::query()->create([
        'slug' => 'sc-child',
        'name' => 'Child',
        'sort_order' => 2,
        'parent_id' => $root->id,
    ]);

    $roots = PluginCategory::query()->root()->pluck('slug')->all();

    expect($roots)->toContain('sc-root')
        ->and($roots)->not->toContain('sc-child');
});

it('scopeOrdered orders by sort_order asc', function (): void {
    PluginCategory::query()->create([
        'slug' => 'so-z',
        'name' => 'Z',
        'sort_order' => 999,
    ]);
    PluginCategory::query()->create([
        'slug' => 'so-a',
        'name' => 'A',
        'sort_order' => -1,
    ]);

    $first = PluginCategory::query()->ordered()->first();
    expect($first?->slug)->toBe('so-a');
});

it('attaches plugins via the categories relationship', function (): void {
    /** @var PluginCategory $cat */
    $cat = PluginCategory::query()->create([
        'slug' => 'rel-cat',
        'name' => 'Rel',
        'sort_order' => 10,
    ]);

    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create([
        'slug' => 'rel-plug',
        'name' => 'plug',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
    ]);

    $cat->plugins()->attach($plugin->id);

    expect($cat->plugins()->count())->toBe(1);
    expect($plugin->categories()->count())->toBe(1);
    expect($plugin->categories()->first()?->slug)->toBe('rel-cat');
});

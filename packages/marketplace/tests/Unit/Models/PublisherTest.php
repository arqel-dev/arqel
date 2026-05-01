<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginInstallation;
use Arqel\Marketplace\Models\PluginReview;
use Arqel\Marketplace\Models\Publisher;

it('exposes the documented fillable attributes', function (): void {
    $publisher = new Publisher;

    expect($publisher->getFillable())->toEqual([
        'slug',
        'user_id',
        'name',
        'bio',
        'avatar_url',
        'website_url',
        'github_url',
        'twitter_handle',
        'verified',
    ]);
});

it('relates to plugins via publisher_id', function (): void {
    $publisher = Publisher::create([
        'slug' => 'acme',
        'name' => 'Acme Corp',
    ]);

    Plugin::create([
        'slug' => 'acme-widget',
        'name' => 'Acme Widget',
        'description' => 'desc',
        'type' => 'widget',
        'github_url' => 'https://github.com/acme/widget',
        'status' => 'published',
        'publisher_id' => $publisher->id,
    ]);

    expect($publisher->plugins()->count())->toBe(1);
    expect($publisher->plugins()->first()?->slug)->toBe('acme-widget');
});

it('computes aggregateStats correctly', function (): void {
    $publisher = Publisher::create([
        'slug' => 'acme',
        'name' => 'Acme Corp',
    ]);

    $a = Plugin::create([
        'slug' => 'plugin-a',
        'name' => 'A',
        'description' => 'desc',
        'type' => 'widget',
        'github_url' => 'https://github.com/x/a',
        'status' => 'published',
        'publisher_id' => $publisher->id,
    ]);

    $b = Plugin::create([
        'slug' => 'plugin-b',
        'name' => 'B',
        'description' => 'desc',
        'type' => 'widget',
        'github_url' => 'https://github.com/x/b',
        'status' => 'published',
        'publisher_id' => $publisher->id,
    ]);

    PluginInstallation::create([
        'plugin_id' => $a->id,
        'installed_at' => now(),
    ]);
    PluginInstallation::create([
        'plugin_id' => $a->id,
        'installed_at' => now(),
    ]);
    PluginInstallation::create([
        'plugin_id' => $b->id,
        'installed_at' => now(),
    ]);

    PluginReview::create([
        'plugin_id' => $a->id,
        'user_id' => 1,
        'stars' => 5,
        'status' => 'published',
    ]);
    PluginReview::create([
        'plugin_id' => $b->id,
        'user_id' => 2,
        'stars' => 3,
        'status' => 'published',
    ]);

    $stats = $publisher->aggregateStats();

    expect($stats['plugins_count'])->toBe(2);
    expect($stats['total_downloads'])->toBe(3);
    expect($stats['avg_rating'])->toBe(4.0);
});

it('returns zeros for publisher with no plugins', function (): void {
    $publisher = Publisher::create([
        'slug' => 'lonely',
        'name' => 'Lonely',
    ]);

    expect($publisher->aggregateStats())->toEqual([
        'plugins_count' => 0,
        'total_downloads' => 0,
        'avg_rating' => 0.0,
    ]);
});

it('scopeVerified filters verified publishers only', function (): void {
    Publisher::create(['slug' => 'a', 'name' => 'A', 'verified' => true]);
    Publisher::create(['slug' => 'b', 'name' => 'B', 'verified' => false]);

    expect(Publisher::query()->verified()->count())->toBe(1);
});

it('scopeWithPlugins filters publishers with at least one published plugin', function (): void {
    $withPlugin = Publisher::create(['slug' => 'a', 'name' => 'A']);
    Publisher::create(['slug' => 'b', 'name' => 'B']);

    Plugin::create([
        'slug' => 'p',
        'name' => 'P',
        'description' => 'd',
        'type' => 'widget',
        'github_url' => 'https://github.com/x/p',
        'status' => 'published',
        'publisher_id' => $withPlugin->id,
    ]);

    expect(Publisher::query()->withPlugins()->count())->toBe(1);
});

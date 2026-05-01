<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginInstallation;
use Arqel\Marketplace\Models\PluginReview;
use Arqel\Marketplace\Services\TrendingScoreCalculator;

function tscPlugin(array $overrides = []): Plugin
{
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create(array_merge([
        'slug' => 'tsc-'.uniqid(),
        'name' => 'tsc',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
    ], $overrides));

    return $plugin;
}

it('returns 0.0 when there are no installations or reviews', function (): void {
    $plugin = tscPlugin();
    $calculator = new TrendingScoreCalculator;

    expect($calculator->calculate($plugin))->toBe(0.0);
});

it('counts installations from last 7 days', function (): void {
    $plugin = tscPlugin();
    PluginInstallation::query()->create([
        'plugin_id' => $plugin->id,
        'installed_at' => now()->subDays(2),
    ]);
    PluginInstallation::query()->create([
        'plugin_id' => $plugin->id,
        'installed_at' => now()->subDays(5),
    ]);
    PluginInstallation::query()->create([
        'plugin_id' => $plugin->id,
        'installed_at' => now()->subDays(20),
    ]);

    $calculator = new TrendingScoreCalculator;
    expect($calculator->calculate($plugin))->toBe(2.0);
});

it('weights recent positive reviews 5x', function (): void {
    $plugin = tscPlugin();
    PluginReview::query()->create([
        'plugin_id' => $plugin->id,
        'user_id' => 1,
        'stars' => 5,
        'status' => 'published',
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);
    PluginReview::query()->create([
        'plugin_id' => $plugin->id,
        'user_id' => 2,
        'stars' => 4,
        'status' => 'published',
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ]);
    // 3-star ignored
    PluginReview::query()->create([
        'plugin_id' => $plugin->id,
        'user_id' => 3,
        'stars' => 3,
        'status' => 'published',
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    $calculator = new TrendingScoreCalculator;
    expect($calculator->calculate($plugin))->toBe(10.0);
});

it('recalculateAll updates trending_score and timestamps for published plugins', function (): void {
    $a = tscPlugin(['slug' => 'tsc-a']);
    $b = tscPlugin(['slug' => 'tsc-b', 'status' => 'draft']);

    PluginInstallation::query()->create([
        'plugin_id' => $a->id,
        'installed_at' => now()->subDay(),
    ]);

    $calculator = new TrendingScoreCalculator;
    $count = $calculator->recalculateAll();

    expect($count)->toBe(1);
    $a->refresh();
    expect($a->trending_score)->toBe(1.0);
    expect($a->trending_score_updated_at)->not->toBeNull();

    $b->refresh();
    expect($b->trending_score)->toBe(0.0);
});

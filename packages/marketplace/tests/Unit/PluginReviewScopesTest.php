<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginReview;

function reviewScopePlugin(): Plugin
{
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create([
        'slug' => 'scope-plugin',
        'name' => 'Scope plugin',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
    ]);

    return $plugin;
}

it('orders by mostHelpful prioritizing helpful_count then score', function (): void {
    $p = reviewScopePlugin();
    $a = PluginReview::query()->create([
        'plugin_id' => $p->id, 'user_id' => 1, 'stars' => 5,
        'helpful_count' => 10, 'unhelpful_count' => 1, 'status' => 'published',
    ]);
    $b = PluginReview::query()->create([
        'plugin_id' => $p->id, 'user_id' => 2, 'stars' => 5,
        'helpful_count' => 50, 'unhelpful_count' => 5, 'status' => 'published',
    ]);
    $c = PluginReview::query()->create([
        'plugin_id' => $p->id, 'user_id' => 3, 'stars' => 5,
        'helpful_count' => 0, 'unhelpful_count' => 0, 'status' => 'published',
    ]);

    $ids = PluginReview::query()->mostHelpful()->pluck('id')->all();
    expect($ids)->toBe([$b->id, $a->id, $c->id]);
});

it('orders by mostRecent created_at desc', function (): void {
    $p = reviewScopePlugin();
    $old = PluginReview::query()->create([
        'plugin_id' => $p->id, 'user_id' => 1, 'stars' => 4, 'status' => 'published',
        'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5),
    ]);
    $new = PluginReview::query()->create([
        'plugin_id' => $p->id, 'user_id' => 2, 'stars' => 4, 'status' => 'published',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $ids = PluginReview::query()->mostRecent()->pluck('id')->all();
    expect($ids[0])->toBe($new->id);
    expect($ids[1])->toBe($old->id);
});

it('orders by highestRated stars desc', function (): void {
    $p = reviewScopePlugin();
    $low = PluginReview::query()->create([
        'plugin_id' => $p->id, 'user_id' => 1, 'stars' => 1, 'status' => 'published',
    ]);
    $high = PluginReview::query()->create([
        'plugin_id' => $p->id, 'user_id' => 2, 'stars' => 5, 'status' => 'published',
    ]);

    $ids = PluginReview::query()->highestRated()->pluck('id')->all();
    expect($ids[0])->toBe($high->id);
    expect($ids[1])->toBe($low->id);
});

it('scopePublished filters out pending and hidden', function (): void {
    $p = reviewScopePlugin();
    PluginReview::query()->create([
        'plugin_id' => $p->id, 'user_id' => 1, 'stars' => 5, 'status' => 'published',
    ]);
    PluginReview::query()->create([
        'plugin_id' => $p->id, 'user_id' => 2, 'stars' => 5, 'status' => 'pending',
    ]);
    PluginReview::query()->create([
        'plugin_id' => $p->id, 'user_id' => 3, 'stars' => 5, 'status' => 'hidden',
    ]);

    expect(PluginReview::query()->published()->count())->toBe(1);
    expect(PluginReview::query()->pending()->count())->toBe(1);
    expect(PluginReview::query()->hidden()->count())->toBe(1);
});

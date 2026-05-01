<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginReview;

function listPlugin(): Plugin
{
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create([
        'slug' => 'list-target',
        'name' => 'List target',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
    ]);

    return $plugin;
}

/**
 * @param array<string, mixed> $overrides
 */
function makeReview(Plugin $plugin, array $overrides = []): PluginReview
{
    /** @var PluginReview $review */
    $review = PluginReview::query()->create(array_merge([
        'plugin_id' => $plugin->id,
        'user_id' => random_int(1, 1_000_000),
        'stars' => 4,
        'comment' => 'ok',
        'status' => 'published',
    ], $overrides));

    return $review;
}

it('orders reviews by helpful by default', function (): void {
    $plugin = listPlugin();
    $low = makeReview($plugin, ['helpful_count' => 1]);
    $high = makeReview($plugin, ['helpful_count' => 50]);
    $mid = makeReview($plugin, ['helpful_count' => 10]);

    $response = $this->getJson('/api/marketplace/plugins/list-target/reviews');
    $response->assertOk();

    $ids = array_column((array) $response->json('data'), 'id');
    expect($ids)->toBe([$high->id, $mid->id, $low->id]);
    expect($response->json('meta.sort'))->toBe('helpful');
});

it('orders reviews by recent when requested', function (): void {
    $plugin = listPlugin();
    $first = makeReview($plugin, ['created_at' => now()->subDays(3)]);
    $second = makeReview($plugin, ['created_at' => now()->subDays(1)]);
    $third = makeReview($plugin, ['created_at' => now()]);

    $response = $this->getJson('/api/marketplace/plugins/list-target/reviews?sort=recent');
    $response->assertOk();

    $ids = array_column((array) $response->json('data'), 'id');
    expect($ids)->toBe([$third->id, $second->id, $first->id]);
});

it('orders reviews by rating when requested', function (): void {
    $plugin = listPlugin();
    $low = makeReview($plugin, ['stars' => 1]);
    $high = makeReview($plugin, ['stars' => 5]);
    $mid = makeReview($plugin, ['stars' => 3]);

    $response = $this->getJson('/api/marketplace/plugins/list-target/reviews?sort=rating');
    $response->assertOk();

    $ids = array_column((array) $response->json('data'), 'id');
    expect($ids)->toBe([$high->id, $mid->id, $low->id]);
});

it('excludes hidden and pending reviews from public list', function (): void {
    $plugin = listPlugin();
    $published = makeReview($plugin, ['status' => 'published']);
    makeReview($plugin, ['status' => 'pending']);
    makeReview($plugin, ['status' => 'hidden']);

    $response = $this->getJson('/api/marketplace/plugins/list-target/reviews');
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    $data = (array) $response->json('data');
    expect($data[0]['id'])->toBe($published->id);
});

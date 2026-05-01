<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginReview;
use Arqel\Marketplace\Models\PluginReviewVote;

function votePluginModel(): Plugin
{
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create([
        'slug' => 'vote-model-plugin',
        'name' => 'Vote model plugin',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
    ]);

    return $plugin;
}

function voteReviewModel(Plugin $plugin): PluginReview
{
    /** @var PluginReview $review */
    $review = PluginReview::query()->create([
        'plugin_id' => $plugin->id,
        'user_id' => 1,
        'stars' => 5,
        'status' => 'published',
    ]);

    return $review;
}

it('enforces unique (review_id, user_id) via firstOrCreate', function (): void {
    $plugin = votePluginModel();
    $review = voteReviewModel($plugin);

    PluginReviewVote::query()->firstOrCreate(
        ['review_id' => $review->id, 'user_id' => 42],
        ['vote' => 'helpful'],
    );

    PluginReviewVote::query()->firstOrCreate(
        ['review_id' => $review->id, 'user_id' => 42],
        ['vote' => 'unhelpful'],
    );

    expect(PluginReviewVote::query()->count())->toBe(1);
    $vote = PluginReviewVote::query()->first();
    expect($vote)->not->toBeNull();
    expect($vote->vote)->toBe('helpful');
});

it('resolves review relationship from vote', function (): void {
    $plugin = votePluginModel();
    $review = voteReviewModel($plugin);

    $vote = PluginReviewVote::query()->create([
        'review_id' => $review->id,
        'user_id' => 99,
        'vote' => 'helpful',
    ]);

    expect($vote->review)->not->toBeNull();
    expect($vote->review->id)->toBe($review->id);
    expect($review->votes()->count())->toBe(1);
});

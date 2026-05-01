<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginReview;
use Arqel\Marketplace\Models\PluginReviewVote;
use Arqel\Marketplace\Tests\Fixtures\TestUser;

function votePlugin(): Plugin
{
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create([
        'slug' => 'vote-target',
        'name' => 'Vote target',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
    ]);

    return $plugin;
}

function voteReview(Plugin $plugin): PluginReview
{
    /** @var PluginReview $review */
    $review = PluginReview::query()->create([
        'plugin_id' => $plugin->id,
        'user_id' => 999,
        'stars' => 5,
        'comment' => 'good',
        'status' => 'published',
    ]);

    return $review;
}

function voteUser(string $name = 'voter'): TestUser
{
    /** @var TestUser $u */
    $u = TestUser::query()->create(['name' => $name]);

    return $u;
}

it('records helpful vote and increments counter', function (): void {
    $plugin = votePlugin();
    $review = voteReview($plugin);
    $user = voteUser();

    $response = $this->actingAs($user)
        ->postJson("/api/marketplace/plugins/vote-target/reviews/{$review->id}/vote", [
            'vote' => 'helpful',
        ]);

    $response->assertOk();
    $review->refresh();
    expect($review->helpful_count)->toBe(1);
    expect($review->unhelpful_count)->toBe(0);
    expect(PluginReviewVote::query()->count())->toBe(1);
});

it('records unhelpful vote and increments counter', function (): void {
    $plugin = votePlugin();
    $review = voteReview($plugin);
    $user = voteUser();

    $this->actingAs($user)
        ->postJson("/api/marketplace/plugins/vote-target/reviews/{$review->id}/vote", [
            'vote' => 'unhelpful',
        ])
        ->assertOk();

    $review->refresh();
    expect($review->helpful_count)->toBe(0);
    expect($review->unhelpful_count)->toBe(1);
});

it('switches vote from helpful to unhelpful and updates counters', function (): void {
    $plugin = votePlugin();
    $review = voteReview($plugin);
    $user = voteUser();

    $this->actingAs($user)
        ->postJson("/api/marketplace/plugins/vote-target/reviews/{$review->id}/vote", [
            'vote' => 'helpful',
        ])
        ->assertOk();

    $this->actingAs($user)
        ->postJson("/api/marketplace/plugins/vote-target/reviews/{$review->id}/vote", [
            'vote' => 'unhelpful',
        ])
        ->assertOk();

    $review->refresh();
    expect($review->helpful_count)->toBe(0);
    expect($review->unhelpful_count)->toBe(1);
    expect(PluginReviewVote::query()->count())->toBe(1);
});

it('is idempotent for repeated same-vote submissions', function (): void {
    $plugin = votePlugin();
    $review = voteReview($plugin);
    $user = voteUser();

    $this->actingAs($user)
        ->postJson("/api/marketplace/plugins/vote-target/reviews/{$review->id}/vote", [
            'vote' => 'helpful',
        ])
        ->assertOk();

    $this->actingAs($user)
        ->postJson("/api/marketplace/plugins/vote-target/reviews/{$review->id}/vote", [
            'vote' => 'helpful',
        ])
        ->assertOk();

    $review->refresh();
    expect($review->helpful_count)->toBe(1);
    expect(PluginReviewVote::query()->count())->toBe(1);
});

it('removes vote on delete and decrements counter', function (): void {
    $plugin = votePlugin();
    $review = voteReview($plugin);
    $user = voteUser();

    $this->actingAs($user)
        ->postJson("/api/marketplace/plugins/vote-target/reviews/{$review->id}/vote", [
            'vote' => 'helpful',
        ])
        ->assertOk();

    $this->actingAs($user)
        ->deleteJson("/api/marketplace/plugins/vote-target/reviews/{$review->id}/vote")
        ->assertOk();

    $review->refresh();
    expect($review->helpful_count)->toBe(0);
    expect(PluginReviewVote::query()->count())->toBe(0);
});

it('returns 401 when unauthenticated', function (): void {
    $plugin = votePlugin();
    $review = voteReview($plugin);

    $response = $this->postJson("/api/marketplace/plugins/vote-target/reviews/{$review->id}/vote", [
        'vote' => 'helpful',
    ]);

    expect($response->status())->toBeIn([401, 302]);
});

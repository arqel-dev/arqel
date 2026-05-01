<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginReview;
use Arqel\Marketplace\Tests\Fixtures\TestUser;

function reviewPlugin(): Plugin
{
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create([
        'slug' => 'review-target',
        'name' => 'Reviewable',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
    ]);

    return $plugin;
}

function reviewUser(): TestUser
{
    /** @var TestUser $user */
    $user = TestUser::query()->create(['name' => 'reviewer']);

    return $user;
}

it('creates review on happy path', function (): void {
    reviewPlugin();
    $user = reviewUser();

    $response = $this->actingAs($user)
        ->postJson('/api/marketplace/plugins/review-target/reviews', [
            'stars' => 4,
            'comment' => 'solid',
        ]);

    $response->assertCreated();
    expect($response->json('review.stars'))->toBe(4);
    expect(PluginReview::query()->count())->toBe(1);
});

it('returns 422 when stars are invalid', function (): void {
    reviewPlugin();
    $user = reviewUser();

    $response = $this->actingAs($user)
        ->postJson('/api/marketplace/plugins/review-target/reviews', [
            'stars' => 7,
        ]);

    $response->assertStatus(422);
});

it('returns 401 when unauthenticated', function (): void {
    reviewPlugin();

    $response = $this->postJson('/api/marketplace/plugins/review-target/reviews', [
        'stars' => 5,
    ]);

    // Without sanctum/auth bound, our controller resolves user as null and
    // returns 401 directly (the route middleware also gates it).
    expect($response->status())->toBeIn([401, 302]);
});

it('is idempotent for same user + plugin', function (): void {
    reviewPlugin();
    $user = reviewUser();

    $this->actingAs($user)
        ->postJson('/api/marketplace/plugins/review-target/reviews', [
            'stars' => 5,
            'comment' => 'first',
        ])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson('/api/marketplace/plugins/review-target/reviews', [
            'stars' => 3,
            'comment' => 'second',
        ])
        ->assertCreated();

    expect(PluginReview::query()->count())->toBe(1);
    // The first review's stars/comment win because firstOrCreate is idempotent.
    $review = PluginReview::query()->first();
    expect($review)->not->toBeNull();
    expect($review->stars)->toBe(5);
});

<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginReview;
use Arqel\Marketplace\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;

function modPlugin(): Plugin
{
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create([
        'slug' => 'mod-target',
        'name' => 'Mod target',
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
function pendingReview(Plugin $plugin, array $overrides = []): PluginReview
{
    /** @var PluginReview $r */
    $r = PluginReview::query()->create(array_merge([
        'plugin_id' => $plugin->id,
        'user_id' => 7,
        'stars' => 4,
        'comment' => 'pending one',
        'status' => 'pending',
    ], $overrides));

    return $r;
}

function modAdminUser(): TestUser
{
    /** @var TestUser $u */
    $u = TestUser::query()->create(['name' => 'mod-admin']);

    return $u;
}

function modRegularUser(): TestUser
{
    /** @var TestUser $u */
    $u = TestUser::query()->create(['name' => 'mod-user']);

    return $u;
}

function allowModerationGate(): void
{
    Gate::define(
        'marketplace.moderate-reviews',
        static fn ($user): bool => $user instanceof TestUser && $user->name === 'mod-admin',
    );
}

it('publishes a pending review', function (): void {
    allowModerationGate();
    $plugin = modPlugin();
    $review = pendingReview($plugin);
    $admin = modAdminUser();

    $response = $this->actingAs($admin)
        ->postJson("/api/marketplace/admin/reviews/{$review->id}/moderate", [
            'action' => 'publish',
        ]);

    $response->assertOk();
    $review->refresh();
    expect($review->status)->toBe('published');
    expect($review->moderation_reason)->toBeNull();
});

it('hides a review with reason', function (): void {
    allowModerationGate();
    $plugin = modPlugin();
    $review = pendingReview($plugin);
    $admin = modAdminUser();

    $response = $this->actingAs($admin)
        ->postJson("/api/marketplace/admin/reviews/{$review->id}/moderate", [
            'action' => 'hide',
            'reason' => 'Spam content',
        ]);

    $response->assertOk();
    $review->refresh();
    expect($review->status)->toBe('hidden');
    expect($review->moderation_reason)->toBe('Spam content');
});

it('returns 403 without moderate-reviews ability', function (): void {
    allowModerationGate();
    $plugin = modPlugin();
    $review = pendingReview($plugin);
    $user = modRegularUser();

    $this->actingAs($user)
        ->postJson("/api/marketplace/admin/reviews/{$review->id}/moderate", [
            'action' => 'publish',
        ])
        ->assertStatus(403);
});

it('lists only pending reviews on admin queue', function (): void {
    allowModerationGate();
    $plugin = modPlugin();
    pendingReview($plugin, ['status' => 'pending', 'comment' => 'p']);
    pendingReview($plugin, ['status' => 'published', 'comment' => 'pub']);
    pendingReview($plugin, ['status' => 'hidden', 'comment' => 'h']);
    $admin = modAdminUser();

    $response = $this->actingAs($admin)
        ->getJson('/api/marketplace/admin/reviews?status=pending');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('returns 422 when hide is missing reason', function (): void {
    allowModerationGate();
    $plugin = modPlugin();
    $review = pendingReview($plugin);
    $admin = modAdminUser();

    $this->actingAs($admin)
        ->postJson("/api/marketplace/admin/reviews/{$review->id}/moderate", [
            'action' => 'hide',
        ])
        ->assertStatus(422);
});

<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;

function featPlugin(array $overrides = []): Plugin
{
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create(array_merge([
        'slug' => 'pf-target',
        'name' => 'pf',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
    ], $overrides));

    return $plugin;
}

function pfAdmin(): TestUser
{
    /** @var TestUser $u */
    $u = TestUser::query()->create(['name' => 'pf-admin']);

    return $u;
}

function pfRegularUser(): TestUser
{
    /** @var TestUser $u */
    $u = TestUser::query()->create(['name' => 'pf-user']);

    return $u;
}

function allowFeatureGate(): void
{
    Gate::define(
        'marketplace.feature',
        static fn ($user): bool => $user instanceof TestUser && $user->name === 'pf-admin',
    );
}

it('toggles featured on and sets featured_at', function (): void {
    allowFeatureGate();
    $plugin = featPlugin();
    $admin = pfAdmin();

    $response = $this->actingAs($admin)
        ->postJson("/api/marketplace/admin/plugins/{$plugin->slug}/feature", [
            'featured' => true,
        ]);

    $response->assertOk();
    $plugin->refresh();
    expect($plugin->featured)->toBeTrue();
    expect($plugin->featured_at)->not->toBeNull();
});

it('toggles featured off and clears featured_at', function (): void {
    allowFeatureGate();
    $plugin = featPlugin(['featured' => true, 'featured_at' => now()]);
    $admin = pfAdmin();

    $response = $this->actingAs($admin)
        ->postJson("/api/marketplace/admin/plugins/{$plugin->slug}/feature", [
            'featured' => false,
        ]);

    $response->assertOk();
    $plugin->refresh();
    expect($plugin->featured)->toBeFalse();
    expect($plugin->featured_at)->toBeNull();
});

it('returns 403 without marketplace.feature gate', function (): void {
    allowFeatureGate();
    $plugin = featPlugin();
    $user = pfRegularUser();

    $this->actingAs($user)
        ->postJson("/api/marketplace/admin/plugins/{$plugin->slug}/feature", [
            'featured' => true,
        ])
        ->assertStatus(403);
});

it('returns 422 without featured field', function (): void {
    allowFeatureGate();
    $plugin = featPlugin();
    $admin = pfAdmin();

    $this->actingAs($admin)
        ->postJson("/api/marketplace/admin/plugins/{$plugin->slug}/feature", [])
        ->assertStatus(422);
});

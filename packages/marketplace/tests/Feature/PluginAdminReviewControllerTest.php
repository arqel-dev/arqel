<?php

declare(strict_types=1);

use Arqel\Marketplace\Events\PluginApproved;
use Arqel\Marketplace\Events\PluginRejected;
use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

function adminUser(): TestUser
{
    /** @var TestUser $u */
    $u = TestUser::query()->create(['name' => 'admin']);

    return $u;
}

function regularUser(): TestUser
{
    /** @var TestUser $u */
    $u = TestUser::query()->create(['name' => 'user']);

    return $u;
}

function pendingPlugin(): Plugin
{
    /** @var Plugin $p */
    $p = Plugin::query()->create([
        'slug' => 'pending-one',
        'name' => 'Pending One',
        'description' => 'A pending plugin under review.',
        'type' => 'widget',
        'github_url' => 'https://github.com/acme/pending-one',
        'composer_package' => 'acme/pending-one',
        'status' => 'pending',
        'submitted_at' => now(),
    ]);

    return $p;
}

function allowAdminGate(): void
{
    Gate::define('marketplace.review', static fn ($user): bool => $user instanceof TestUser && $user->name === 'admin');
}

it('approves a pending plugin and dispatches event', function (): void {
    Event::fake();
    allowAdminGate();
    pendingPlugin();
    $admin = adminUser();

    $response = $this->actingAs($admin)
        ->postJson('/api/marketplace/admin/plugins/pending-one/review', [
            'action' => 'approve',
        ]);

    $response->assertOk();
    expect($response->json('plugin.status'))->toBe('published');

    $plugin = Plugin::query()->where('slug', 'pending-one')->firstOrFail();
    expect($plugin->status)->toBe('published');
    expect($plugin->reviewed_at)->not->toBeNull();
    expect($plugin->reviewed_by_user_id)->toBe($admin->id);

    Event::assertDispatched(PluginApproved::class);
});

it('rejects a pending plugin with reason', function (): void {
    Event::fake();
    allowAdminGate();
    pendingPlugin();
    $admin = adminUser();

    $response = $this->actingAs($admin)
        ->postJson('/api/marketplace/admin/plugins/pending-one/review', [
            'action' => 'reject',
            'rejection_reason' => 'Missing tests and README.',
        ]);

    $response->assertOk();

    $plugin = Plugin::query()->where('slug', 'pending-one')->firstOrFail();
    expect($plugin->status)->toBe('archived');
    expect($plugin->rejection_reason)->toBe('Missing tests and README.');

    Event::assertDispatched(PluginRejected::class);
});

it('returns 403 when user lacks marketplace.review ability', function (): void {
    allowAdminGate();
    pendingPlugin();
    $user = regularUser();

    $response = $this->actingAs($user)
        ->postJson('/api/marketplace/admin/plugins/pending-one/review', [
            'action' => 'approve',
        ]);

    $response->assertStatus(403);
});

it('returns 422 when reject is missing reason', function (): void {
    allowAdminGate();
    pendingPlugin();
    $admin = adminUser();

    $response = $this->actingAs($admin)
        ->postJson('/api/marketplace/admin/plugins/pending-one/review', [
            'action' => 'reject',
        ]);

    $response->assertStatus(422);
});

it('does not dispatch approved event on reject', function (): void {
    Event::fake();
    allowAdminGate();
    pendingPlugin();
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/marketplace/admin/plugins/pending-one/review', [
            'action' => 'reject',
            'rejection_reason' => 'Quality below bar.',
        ])
        ->assertOk();

    Event::assertNotDispatched(PluginApproved::class);
    Event::assertDispatched(PluginRejected::class);
});

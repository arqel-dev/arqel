<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;

function adminListUser(): TestUser
{
    /** @var TestUser $u */
    $u = TestUser::query()->create(['name' => 'admin']);

    return $u;
}

function regListUser(): TestUser
{
    /** @var TestUser $u */
    $u = TestUser::query()->create(['name' => 'user']);

    return $u;
}

function allowAdminListGate(): void
{
    Gate::define('marketplace.review', static fn ($user): bool => $user instanceof TestUser && $user->name === 'admin');
}

function makeAdminPlugin(string $slug, string $status): void
{
    Plugin::query()->create([
        'slug' => $slug,
        'name' => $slug,
        'description' => 'desc for '.$slug,
        'type' => 'widget',
        'github_url' => 'https://github.com/acme/'.$slug,
        'status' => $status,
        'submitted_at' => now(),
    ]);
}

it('lists only pending plugins by default', function (): void {
    allowAdminListGate();
    makeAdminPlugin('a-pending', 'pending');
    makeAdminPlugin('b-published', 'published');
    makeAdminPlugin('c-pending', 'pending');

    $response = $this->actingAs(adminListUser())
        ->getJson('/api/marketplace/admin/plugins');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(2);
    /** @var array<int, array{slug: string}> $rows */
    $rows = $response->json('data');
    $slugs = array_map(static fn (array $row): string => $row['slug'], $rows);
    expect($slugs)->toContain('a-pending');
    expect($slugs)->toContain('c-pending');
    expect($slugs)->not->toContain('b-published');
});

it('returns 403 without marketplace.review ability', function (): void {
    allowAdminListGate();
    makeAdminPlugin('x-pending', 'pending');

    $response = $this->actingAs(regListUser())
        ->getJson('/api/marketplace/admin/plugins');

    $response->assertStatus(403);
});

it('paginates with custom per_page', function (): void {
    allowAdminListGate();
    for ($i = 0; $i < 5; $i++) {
        makeAdminPlugin("p-{$i}", 'pending');
    }

    $response = $this->actingAs(adminListUser())
        ->getJson('/api/marketplace/admin/plugins?per_page=2');

    $response->assertOk();
    expect($response->json('meta.per_page'))->toBe(2);
    expect($response->json('meta.total'))->toBe(5);
    expect(count($response->json('data')))->toBe(2);
});

<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\SecurityScan;
use Arqel\Marketplace\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;

function adminScanUser(): TestUser
{
    /** @var TestUser $u */
    $u = TestUser::query()->create(['name' => 'admin']);

    return $u;
}

function regScanUser(): TestUser
{
    /** @var TestUser $u */
    $u = TestUser::query()->create(['name' => 'user']);

    return $u;
}

function allowSecurityScansGate(): void
{
    Gate::define('marketplace.security-scans', static fn ($user): bool => $user instanceof TestUser && $user->name === 'admin');
}

function makeScanRow(string $slug, string $status, ?string $severity = null): SecurityScan
{
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create([
        'slug' => $slug,
        'name' => $slug,
        'description' => 'desc',
        'type' => 'widget',
        'github_url' => 'https://github.com/acme/'.$slug,
        'license' => 'MIT',
        'status' => 'published',
    ]);

    /** @var SecurityScan $scan */
    $scan = SecurityScan::query()->create([
        'plugin_id' => $plugin->id,
        'scan_started_at' => now(),
        'scan_completed_at' => now(),
        'status' => $status,
        'severity' => $severity,
        'findings' => [],
        'scanner_version' => '1.0',
    ]);

    return $scan;
}

it('lists scans paginated for admin', function (): void {
    allowSecurityScansGate();
    makeScanRow('p-a', 'passed');
    makeScanRow('p-b', 'flagged', 'high');

    $response = $this->actingAs(adminScanUser())
        ->getJson('/api/marketplace/admin/security-scans');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(2);
});

it('filters by status', function (): void {
    allowSecurityScansGate();
    makeScanRow('p-a', 'passed');
    makeScanRow('p-b', 'flagged', 'high');
    makeScanRow('p-c', 'flagged', 'medium');

    $response = $this->actingAs(adminScanUser())
        ->getJson('/api/marketplace/admin/security-scans?status=flagged');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(2);
});

it('returns 403 without marketplace.security-scans ability', function (): void {
    allowSecurityScansGate();
    makeScanRow('p-a', 'passed');

    $response = $this->actingAs(regScanUser())
        ->getJson('/api/marketplace/admin/security-scans');

    $response->assertStatus(403);
});

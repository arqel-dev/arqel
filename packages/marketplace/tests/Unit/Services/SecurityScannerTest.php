<?php

declare(strict_types=1);

use Arqel\Marketplace\Contracts\Advisory;
use Arqel\Marketplace\Events\PluginAutoDelistedEvent;
use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\SecurityScan;
use Arqel\Marketplace\Services\SecurityScanner;
use Arqel\Marketplace\Tests\Fixtures\FakeVulnerabilityDatabase;
use Illuminate\Support\Facades\Event;

function makeScanPlugin(array $overrides = []): Plugin
{
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create(array_merge([
        'slug' => 'scan-plugin-'.uniqid(),
        'name' => 'Scan Plugin',
        'description' => 'description for scan plugin tests',
        'type' => 'widget',
        'github_url' => 'https://github.com/acme/x',
        'license' => 'MIT',
        'composer_package' => 'acme/x',
        'status' => 'published',
    ], $overrides));

    return $plugin;
}

it('returns passed for plugin with no findings', function (): void {
    $scanner = new SecurityScanner(new FakeVulnerabilityDatabase);
    $plugin = makeScanPlugin();

    $scan = $scanner->scan($plugin);

    expect($scan->status)->toBe('passed');
    expect($scan->severity)->toBeNull();
    expect($scan->findings)->toBe([]);
    expect($scan->scan_completed_at)->not->toBeNull();
});

it('marks status failed and auto-delists when critical advisory is found', function (): void {
    Event::fake([PluginAutoDelistedEvent::class]);

    $vulnDb = new FakeVulnerabilityDatabase([
        'composer:acme/critical' => [
            new Advisory('GHSA-1', 'critical', 'Remote code execution', '>=1.0.1'),
        ],
    ]);
    $scanner = new SecurityScanner($vulnDb);

    $plugin = makeScanPlugin([
        'slug' => 'critical-plugin',
        'composer_package' => 'acme/critical',
    ]);

    $scan = $scanner->scan($plugin);

    expect($scan->status)->toBe('failed');
    expect($scan->severity)->toBe('critical');

    $plugin->refresh();
    expect($plugin->status)->toBe('archived');

    Event::assertDispatched(PluginAutoDelistedEvent::class, fn (PluginAutoDelistedEvent $e): bool => $e->plugin->id === $plugin->id && $e->scan->id === $scan->id);
});

it('passes when license is in allow-list (MIT)', function (): void {
    $scanner = new SecurityScanner(new FakeVulnerabilityDatabase);
    $plugin = makeScanPlugin(['license' => 'MIT']);

    $scan = $scanner->scan($plugin);

    expect($scan->status)->toBe('passed');
    expect($scan->findings)->toBe([]);
});

it('flags low warning when license is proprietary', function (): void {
    $scanner = new SecurityScanner(new FakeVulnerabilityDatabase);
    $plugin = makeScanPlugin(['license' => 'Proprietary']);

    $scan = $scanner->scan($plugin);

    expect($scan->status)->toBe('passed'); // low only → passed
    expect($scan->severity)->toBe('low');
    expect($scan->findings)->toHaveCount(1);
    $findings = $scan->findings ?? [];
    expect($findings[0]['type'] ?? null)->toBe('license-warning');
});

it('rolls up severity to the maximum found across findings', function (): void {
    $vulnDb = new FakeVulnerabilityDatabase([
        'composer:acme/multi' => [
            new Advisory('GHSA-low', 'low', 'minor', '>=2'),
            new Advisory('GHSA-high', 'high', 'serious', '>=2'),
            new Advisory('GHSA-medium', 'medium', 'moderate', '>=2'),
        ],
    ]);
    $scanner = new SecurityScanner($vulnDb);
    $plugin = makeScanPlugin(['composer_package' => 'acme/multi']);

    $scan = $scanner->scan($plugin);

    expect($scan->severity)->toBe('high');
    expect($scan->status)->toBe('flagged');
});

it('persists scan_started_at and status running before completion', function (): void {
    $scanner = new SecurityScanner(new FakeVulnerabilityDatabase);
    $plugin = makeScanPlugin();

    $before = now();
    $scan = $scanner->scan($plugin);

    expect($scan->scan_started_at)->not->toBeNull();
    expect($scan->scan_started_at->greaterThanOrEqualTo($before->subSecond()))->toBeTrue();
    // Final status is passed but a row existed in 'running' state before completion.
    expect(SecurityScan::query()->where('plugin_id', $plugin->id)->count())->toBe(1);
});

it('dispatches PluginAutoDelistedEvent only for critical published plugins', function (): void {
    Event::fake([PluginAutoDelistedEvent::class]);

    $vulnDb = new FakeVulnerabilityDatabase([
        'composer:acme/already-archived' => [
            new Advisory('GHSA-x', 'critical', 'rce', '>=2'),
        ],
    ]);
    $scanner = new SecurityScanner($vulnDb);

    $plugin = makeScanPlugin([
        'slug' => 'already-archived',
        'composer_package' => 'acme/already-archived',
        'status' => 'archived',
    ]);

    $scanner->scan($plugin);

    Event::assertNotDispatched(PluginAutoDelistedEvent::class);
});

it('looks up both composer and npm packages', function (): void {
    $vulnDb = new FakeVulnerabilityDatabase([
        'npm:@acme/widget' => [
            new Advisory('GHSA-npm', 'medium', 'xss', '>=2'),
        ],
    ]);
    $scanner = new SecurityScanner($vulnDb);

    $plugin = makeScanPlugin([
        'slug' => 'multi-eco',
        'composer_package' => 'acme/widget',
        'npm_package' => '@acme/widget',
    ]);

    $scan = $scanner->scan($plugin);

    expect($scan->status)->toBe('flagged');
    expect($scan->severity)->toBe('medium');
});

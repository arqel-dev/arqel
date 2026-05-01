<?php

declare(strict_types=1);

use Arqel\Marketplace\Contracts\Advisory;
use Arqel\Marketplace\Contracts\VulnerabilityDatabase;
use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\SecurityScan;
use Arqel\Marketplace\Tests\Fixtures\FakeVulnerabilityDatabase;

function makeCmdPlugin(string $slug, string $status = 'published', array $extra = []): Plugin
{
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create(array_merge([
        'slug' => $slug,
        'name' => $slug,
        'description' => 'desc for '.$slug,
        'type' => 'widget',
        'github_url' => 'https://github.com/acme/'.$slug,
        'license' => 'MIT',
        'composer_package' => 'acme/'.$slug,
        'status' => $status,
    ], $extra));

    return $plugin;
}

it('registers the arqel:marketplace:scan command', function (): void {
    $this->app->instance(VulnerabilityDatabase::class, new FakeVulnerabilityDatabase);

    $this->artisan('arqel:marketplace:scan')->assertSuccessful();
});

it('scans only published plugins by default', function (): void {
    $this->app->instance(VulnerabilityDatabase::class, new FakeVulnerabilityDatabase);

    makeCmdPlugin('pub-a', 'published');
    makeCmdPlugin('pub-b', 'published');
    makeCmdPlugin('draft-x', 'draft');

    $this->artisan('arqel:marketplace:scan')
        ->expectsOutputToContain('Scanned 2 plugins.')
        ->assertSuccessful();

    expect(SecurityScan::query()->count())->toBe(2);
});

it('filters by plugin slug when --plugin is provided', function (): void {
    $this->app->instance(VulnerabilityDatabase::class, new FakeVulnerabilityDatabase);

    makeCmdPlugin('only-me', 'published');
    makeCmdPlugin('not-me', 'published');

    $this->artisan('arqel:marketplace:scan', ['--plugin' => 'only-me'])
        ->expectsOutputToContain('Scanned 1 plugins.')
        ->assertSuccessful();

    expect(SecurityScan::query()->count())->toBe(1);
});

it('does not persist scans on --dry-run', function (): void {
    $vulnDb = new FakeVulnerabilityDatabase([
        'composer:acme/dry-a' => [
            new Advisory('GHSA-1', 'high', 'serious', '>=2'),
        ],
    ]);
    $this->app->instance(VulnerabilityDatabase::class, $vulnDb);

    makeCmdPlugin('dry-a', 'published');

    $this->artisan('arqel:marketplace:scan', ['--dry-run' => true])
        ->expectsOutputToContain('1 high')
        ->assertSuccessful();

    expect(SecurityScan::query()->count())->toBe(0);
});

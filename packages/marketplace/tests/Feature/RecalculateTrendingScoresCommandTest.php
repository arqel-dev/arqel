<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginInstallation;

it('registers the arqel:marketplace:trending command', function (): void {
    $this->artisan('arqel:marketplace:trending')->assertSuccessful();
});

it('recalculates and stores trending_score for published plugins', function (): void {
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create([
        'slug' => 'cmd-pub',
        'name' => 'cmd',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
    ]);

    PluginInstallation::query()->create([
        'plugin_id' => $plugin->id,
        'installed_at' => now()->subDay(),
    ]);

    $this->artisan('arqel:marketplace:trending')
        ->expectsOutputToContain('Updated 1 plugins.')
        ->assertSuccessful();

    $plugin->refresh();
    expect($plugin->trending_score)->toBe(1.0);
    expect($plugin->trending_score_updated_at)->not->toBeNull();
});

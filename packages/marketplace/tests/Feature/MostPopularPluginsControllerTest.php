<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginInstallation;

function popularPlugin(array $overrides = []): Plugin
{
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create(array_merge([
        'slug' => 'pop-'.uniqid(),
        'name' => 'Pop',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
    ], $overrides));

    return $plugin;
}

function installFor(Plugin $plugin, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        PluginInstallation::query()->create([
            'plugin_id' => $plugin->id,
            'installed_at' => now()->subDays($i),
        ]);
    }
}

it('orders by installations count desc', function (): void {
    $a = popularPlugin(['slug' => 'pop-a']);
    $b = popularPlugin(['slug' => 'pop-b']);
    $c = popularPlugin(['slug' => 'pop-c']);

    installFor($a, 3);
    installFor($b, 10);
    installFor($c, 5);

    $response = $this->getJson('/api/marketplace/popular');

    $response->assertOk();
    $slugs = array_column($response->json('data'), 'slug');
    expect($slugs[0])->toBe('pop-b');
    expect($slugs[1])->toBe('pop-c');
});

it('limits to 20 plugins', function (): void {
    for ($i = 0; $i < 25; $i++) {
        $p = popularPlugin(['slug' => "pop-{$i}"]);
        installFor($p, $i + 1);
    }

    $response = $this->getJson('/api/marketplace/popular');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(20);
});

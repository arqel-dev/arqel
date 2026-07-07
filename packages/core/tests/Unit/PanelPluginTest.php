<?php

declare(strict_types=1);

use Arqel\Core\Contracts\Plugin;
use Arqel\Core\Panel\Panel;
use Arqel\Core\Tests\Fixtures\Plugins\FixturePlugin;
use Arqel\Core\Tests\Fixtures\Resources\PostResource;

it('builds a plugin via the CreatesPlugin make() helper', function (): void {
    $plugin = FixturePlugin::make();

    expect($plugin)->toBeInstanceOf(Plugin::class)
        ->and($plugin->getId())->toBe('fixture');
});

it('calls register() eagerly and mutates the panel', function (): void {
    $panel = new Panel('admin');
    $plugin = FixturePlugin::make();

    $panel->plugin($plugin);

    expect($plugin->registered)->toBeTrue()
        ->and($panel->getResources())->toContain(PostResource::class)
        ->and($panel->getPlugin('fixture'))->toBe($plugin);
});

it('keys plugins by id so the last registration wins', function (): void {
    $panel = new Panel('admin');
    $first = FixturePlugin::make();
    $second = FixturePlugin::make();

    $panel->plugin($first)->plugin($second);

    expect($panel->getPlugins())->toHaveCount(1)
        ->and($panel->getPlugin('fixture'))->toBe($second);
});

it('registers a batch of plugins in insertion order', function (): void {
    $panel = new Panel('admin');
    $plugin = FixturePlugin::make();

    $panel->plugins([$plugin]);

    expect($plugin->registered)->toBeTrue()
        ->and($panel->getPlugins())->toHaveKey('fixture');
});

it('returns null for an unknown plugin id', function (): void {
    $panel = new Panel('admin');

    expect($panel->getPlugin('missing'))->toBeNull();
});

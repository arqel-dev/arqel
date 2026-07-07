<?php

declare(strict_types=1);

use Arqel\Core\Contracts\Plugin;
use Arqel\Core\Tests\Fixtures\Plugins\FixturePlugin;

it('builds a plugin via the CreatesPlugin make() helper', function (): void {
    $plugin = FixturePlugin::make();

    expect($plugin)->toBeInstanceOf(Plugin::class)
        ->and($plugin->getId())->toBe('fixture');
});

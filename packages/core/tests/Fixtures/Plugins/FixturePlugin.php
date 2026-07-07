<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Plugins;

use Arqel\Core\Contracts\Plugin;
use Arqel\Core\Panel\Concerns\CreatesPlugin;
use Arqel\Core\Panel\Panel;
use Arqel\Core\Tests\Fixtures\Resources\PostResource;

final class FixturePlugin implements Plugin
{
    use CreatesPlugin;

    public bool $registered = false;

    public bool $booted = false;

    public function getId(): string
    {
        return 'fixture';
    }

    public function register(Panel $panel): void
    {
        $this->registered = true;
        $panel->resources([PostResource::class]);
    }

    public function boot(Panel $panel): void
    {
        $this->booted = true;
    }
}

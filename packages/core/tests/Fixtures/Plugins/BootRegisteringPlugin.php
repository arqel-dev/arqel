<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Plugins;

use Arqel\Core\Contracts\Plugin;
use Arqel\Core\Panel\Concerns\CreatesPlugin;
use Arqel\Core\Panel\Panel;
use Arqel\Core\Tests\Fixtures\Resources\UserResource;

/**
 * Prova a ordem do lifecycle: registra o resource só em boot().
 * Como bootPanelPlugins() roda ANTES de syncPanelResourcesIntoRegistry(),
 * o UserResource deve acabar no ResourceRegistry.
 */
final class BootRegisteringPlugin implements Plugin
{
    use CreatesPlugin;

    public function getId(): string
    {
        return 'boot-registering';
    }

    public function register(Panel $panel): void
    {
        // intencionalmente vazio — o registro acontece em boot()
    }

    public function boot(Panel $panel): void
    {
        $panel->resources([UserResource::class]);
    }
}

<?php

declare(strict_types=1);

namespace App\Arqel\Plugins;

use App\Arqel\Resources\AuthorResource;
use Arqel\Core\Contracts\Plugin;
use Arqel\Core\Panel\Concerns\CreatesPlugin;
use Arqel\Core\Panel\Panel;

/**
 * Plugin de dogfood: demonstra o registro in-code de um resource via a
 * Plugin API. Empacota o AuthorResource (antes registrado direto no
 * ServiceProvider) para provar que o registro via plugin é equivalente.
 */
final class ShowcasePlugin implements Plugin
{
    use CreatesPlugin;

    public function getId(): string
    {
        return 'showcase';
    }

    public function register(Panel $panel): void
    {
        // Aditivo: `resources()` substitui o array inteiro, então
        // preservamos os já declarados no panel antes de acrescentar.
        $panel->resources([...$panel->getResources(), AuthorResource::class]);
    }

    public function boot(Panel $panel): void
    {
        // sem efeitos de boot neste exemplo
    }
}

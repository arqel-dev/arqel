<?php

declare(strict_types=1);

namespace Arqel\Widgets\Tests\Fixtures;

use Arqel\Core\Contracts\Plugin;
use Arqel\Core\Panel\Concerns\CreatesPlugin;
use Arqel\Core\Panel\Panel;

/**
 * Plugin de teste que injeta um widget no Panel durante `boot()`.
 *
 * Prova a garantia de ordem que o bridge depende: o sync de widgets
 * roda depois de `bootPanelPlugins()` do core, então widgets que só
 * existem a partir do boot de um plugin ainda alcançam o dashboard.
 */
final class WidgetPlugin implements Plugin
{
    use CreatesPlugin;

    public function getId(): string
    {
        return 'test-widget-plugin';
    }

    public function register(Panel $panel): void
    {
        // Nada aqui de propósito: o widget entra em boot(), que é o
        // caso difícil.
    }

    public function boot(Panel $panel): void
    {
        // `Panel::widgets()` substitui a lista, então o spread preserva
        // o que o painel já declarava.
        $panel->widgets([...$panel->getWidgets(), SecondaryWidget::class]);
    }
}

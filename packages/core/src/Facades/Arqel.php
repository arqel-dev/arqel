<?php

declare(strict_types=1);

namespace Arqel\Core\Facades;

use Arqel\Core\Panel\Panel;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for the global Arqel entry point.
 *
 * Resolves to the {@see PanelRegistry} singleton via the `arqel`
 * container alias declared in {@see \Arqel\Core\ArqelServiceProvider}.
 *
 * @method static Panel panel(string $id)
 * @method static void setCurrent(string $id)
 * @method static ?Panel getCurrent()
 * @method static array<string, Panel> all()
 * @method static bool has(string $id)
 * @method static void clear()
 */
final class Arqel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'arqel';
    }
}

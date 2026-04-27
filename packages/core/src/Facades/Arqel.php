<?php

declare(strict_types=1);

namespace Arqel\Core\Facades;

use Arqel\Core\Registries\PanelRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for the global Arqel entry point.
 *
 * Resolves to the {@see PanelRegistry} singleton via the `arqel`
 * container alias declared in {@see \Arqel\Core\ArqelServiceProvider}.
 */
final class Arqel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'arqel';
    }
}

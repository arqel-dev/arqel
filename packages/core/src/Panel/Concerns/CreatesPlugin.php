<?php

declare(strict_types=1);

namespace Arqel\Core\Panel\Concerns;

/**
 * Açúcar opcional para plugins: `MyPlugin::make()`.
 *
 * Não é obrigatório para implementar o contrato Plugin — é apenas
 * conveniência para a cadeia fluente `Panel::plugin(MyPlugin::make())`.
 */
trait CreatesPlugin
{
    public static function make(): static
    {
        return new static;
    }
}

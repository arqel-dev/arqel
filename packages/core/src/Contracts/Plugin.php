<?php

declare(strict_types=1);

namespace Arqel\Core\Contracts;

use Arqel\Core\Panel\Panel;

/**
 * Contrato de um plugin in-code do Arqel.
 *
 * Um plugin injeta conteúdo num Panel programaticamente. `register()`
 * roda eager (no momento em que `Panel::plugin()` é chamado, dentro do
 * boot do ServiceProvider do app). `boot()` roda depois, no
 * `$this->app->booted()` do ArqelServiceProvider, ANTES do sync de
 * resources — então resources adicionados em `boot()` ainda viram rota.
 */
interface Plugin
{
    /** Id estável e único por panel (registrar 2x o mesmo id substitui). */
    public function getId(): string;

    /** Muta o Panel (resources/navigationGroups/middleware). Roda eager. */
    public function register(Panel $panel): void;

    /** Efeitos após todos os plugins registrarem. Roda antes do sync. */
    public function boot(Panel $panel): void;
}

<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Plugins;

use Arqel\Core\Contracts\Plugin;
use Arqel\Core\Panel\Concerns\CreatesPlugin;
use Arqel\Core\Panel\Panel;
use Arqel\Core\Tests\Fixtures\Resources\UserResource;

/**
 * Prova a composição real de N plugins: registra o seu resource de
 * forma ADITIVA (`[...$panel->getResources(), X]`), preservando o que
 * um plugin anterior (de outro id) já tenha adicionado ao Panel.
 *
 * Ao contrário de `FixturePlugin::register()`, que substitui o array
 * de resources do Panel (`resources([PostResource::class])`), este
 * fixture é o que um plugin bem-comportado deve fazer quando pode
 * coexistir com outros plugins no mesmo Panel.
 */
final class AdditiveFixturePlugin implements Plugin
{
    use CreatesPlugin;

    public function getId(): string
    {
        return 'additive-fixture';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([...$panel->getResources(), UserResource::class]);
    }

    public function boot(Panel $panel): void
    {
        // sem efeitos colaterais — usado apenas para provar composição.
    }
}

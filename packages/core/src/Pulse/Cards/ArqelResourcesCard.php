<?php

declare(strict_types=1);

namespace Arqel\Core\Pulse\Cards;

use Arqel\Core\Resources\ResourceRegistry;
use Illuminate\Contracts\View\View;
use Throwable;

/**
 * Pulse card: total Resource classes registered (LCLOUD-003).
 *
 * Reads the singleton {@see ResourceRegistry} and renders the count.
 * Defensive: if the registry is unbindable (very edge case), the card
 * renders zero rather than throwing.
 */
final class ArqelResourcesCard extends \Laravel\Pulse\Livewire\Card
{
    public function render(): View
    {
        $count = 0;

        try {
            $registry = app(ResourceRegistry::class);
            assert($registry instanceof ResourceRegistry);
            $count = count($registry->all());
        } catch (Throwable) {
            // keep zero
        }

        return view('arqel::pulse.resources', ['count' => $count]);
    }
}

<?php

declare(strict_types=1);

/**
 * Pulse/Livewire stub bridge (LCLOUD-003).
 *
 * `arqel/core` does NOT hard-depend on `laravel/pulse` nor on
 * `livewire/livewire`. The Pulse cards under
 * `Arqel\Core\Pulse\Cards\*` extend `Laravel\Pulse\Livewire\Card`
 * (which itself extends `Livewire\Component`). When neither package
 * is present, those parent classes do not exist and autoloading any
 * card file would fatal.
 *
 * This bridge is autoloaded once at startup (via composer `files`)
 * and aliases the absent parents to lightweight stubs in
 * `Arqel\Core\Pulse\Stubs`. Real rendering is guarded by
 * `PulseIntegration::isAvailable()` at registration time.
 */
if (! class_exists('Livewire\\Component', false)) {
    \class_alias(
        \Arqel\Core\Pulse\Stubs\LivewireComponentStub::class,
        'Livewire\\Component',
    );
}

if (! class_exists('Laravel\\Pulse\\Livewire\\Card', false)) {
    \class_alias(
        \Arqel\Core\Pulse\Stubs\PulseCardStub::class,
        'Laravel\\Pulse\\Livewire\\Card',
    );
}

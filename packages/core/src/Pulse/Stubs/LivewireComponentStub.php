<?php

declare(strict_types=1);

namespace Arqel\Core\Pulse\Stubs;

/**
 * Placeholder used as a stand-in for `Livewire\Component` when the
 * `livewire/livewire` package is not installed.
 *
 * `arqel-dev/core` does not hard-depend on Livewire; we only need a
 * concrete parent class so that the Pulse cards can be autoloaded by
 * static analysis, tests and generators on a runtime without Pulse.
 *
 * Real rendering is guarded at the registration site by
 * {@see \Arqel\Core\Pulse\PulseIntegration::isAvailable()}, so this
 * stub is never instantiated by Livewire itself.
 */
abstract class LivewireComponentStub {}

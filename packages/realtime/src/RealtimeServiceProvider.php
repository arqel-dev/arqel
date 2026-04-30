<?php

declare(strict_types=1);

namespace Arqel\Realtime;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered provider for `arqel/realtime`.
 *
 * RT-001 ships the package skeleton:
 *
 * - Publishable config `config/arqel-realtime.php` (channel prefix,
 *   broadcasting connection alias, auto-dispatch toggles).
 * - {@see Events\ResourceUpdated} broadcast event (RT-002).
 * - {@see Concerns\BroadcastsResourceUpdates} trait that
 *   Resource subclasses opt into to emit the event from `afterUpdate`.
 *
 * Reverb integration is intentionally `suggest`-level — `laravel/reverb`
 * is the recommended transport but not required. Any broadcaster that
 * implements `BroadcastManager` works out of the box.
 */
final class RealtimeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('arqel-realtime')
            ->hasConfigFile('arqel-realtime');
    }
}

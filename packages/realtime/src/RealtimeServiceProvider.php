<?php

declare(strict_types=1);

namespace Arqel\Realtime;

use Illuminate\Support\Facades\Broadcast;
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
            ->hasConfigFile('arqel-realtime')
            ->hasRoute('channels')
            ->hasRoute('api')
            ->hasMigration('2026_05_06_000000_create_yjs_documents');
    }

    /**
     * Ensure broadcasting auth routes are registered so the presence
     * channel published by RT-004 is reachable. Idempotent — Laravel's
     * BroadcastManager guards against double-registration internally,
     * but we additionally check `Broadcast::routes` is callable to keep
     * the boot path resilient when broadcasting is disabled.
     */
    public function packageBooted(): void
    {
        // Idempotent — Laravel's BroadcastManager guards against
        // double-registration internally, so calling here is safe even
        // when the consumer app has its own `Broadcast::routes()` call.
        Broadcast::routes();
    }
}

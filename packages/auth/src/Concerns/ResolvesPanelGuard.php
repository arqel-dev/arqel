<?php

declare(strict_types=1);

namespace Arqel\Auth\Concerns;

use Arqel\Core\Panel\PanelRegistry;

/**
 * Resolve the auth guard that the active Arqel panel authenticates
 * against (#139).
 *
 * Source of truth, in order:
 *   1. The current panel's `Panel::authGuard(...)` setting, when a
 *      panel is registered and elected as current.
 *   2. The `arqel.auth.guard` config key (default `web`) as a
 *      request-time fallback for contexts where no panel has been
 *      elected yet.
 *
 * The default of `web` preserves the historical behaviour: a panel
 * without `->authGuard(...)` keeps authenticating against the
 * application's default guard.
 */
trait ResolvesPanelGuard
{
    protected function resolvePanelGuard(): string
    {
        if (app()->bound(PanelRegistry::class)) {
            $panel = app(PanelRegistry::class)->getCurrent();

            if ($panel !== null) {
                return $panel->getAuthGuard();
            }
        }

        $configured = config('arqel.auth.guard', 'web');

        return is_string($configured) && $configured !== '' ? $configured : 'web';
    }
}

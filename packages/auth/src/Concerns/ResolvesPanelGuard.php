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

    /**
     * Resolve the Laravel password broker that backs the panel's
     * configured guard (#191).
     *
     * Mapping, in order:
     *   1. From the panel guard, read its provider
     *      (`config("auth.guards.{$guard}.provider")`).
     *   2. Find the password broker whose `provider` matches that provider
     *      (`config('auth.passwords')` entry).
     *   3. Fall back to a broker named exactly after the provider.
     *   4. Fall back to `'users'`.
     *
     * The default `web` guard maps to the `users` provider, which the
     * default `users` broker already targets, so the default path resolves
     * to `'users'` unchanged.
     */
    protected function resolvePasswordBroker(): string
    {
        $guard = $this->resolvePanelGuard();

        $provider = config("auth.guards.{$guard}.provider", 'users');
        $providerKey = is_string($provider) && $provider !== '' ? $provider : 'users';

        $brokers = config('auth.passwords');

        if (is_array($brokers)) {
            foreach ($brokers as $name => $config) {
                if (is_array($config) && ($config['provider'] ?? null) === $providerKey) {
                    return (string) $name;
                }
            }

            if (array_key_exists($providerKey, $brokers)) {
                return $providerKey;
            }
        }

        return 'users';
    }
}

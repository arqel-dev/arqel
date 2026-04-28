<?php

declare(strict_types=1);

use Arqel\Auth\AbilityRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

if (! function_exists('arqel_can')) {
    /**
     * Resolve an ability for the active user.
     *
     * Looks up the registered global/computed entries in the
     * `AbilityRegistry` snapshot first; falls back to Laravel's
     * Gate when the ability isn't registered there. Returns false
     * for guests.
     */
    function arqel_can(string $ability, mixed $arguments = null): bool
    {
        $user = Auth::user();

        if (! $user instanceof Authenticatable) {
            return false;
        }

        if (app()->bound(AbilityRegistry::class)) {
            $snapshot = app(AbilityRegistry::class)->resolveForUser($user);
            if (array_key_exists($ability, $snapshot)) {
                return $snapshot[$ability];
            }
        }

        return Gate::forUser($user)->allows($ability, $arguments);
    }
}

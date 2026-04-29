<?php

declare(strict_types=1);

namespace Arqel\Tenant\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Fired when `TenantManager` adopts a new current tenant —
 * either through the resolver, an explicit `set()`, or entry
 * into a `runFor()` scope.
 *
 * Listeners can hook analytics, logging, cache warming, etc.
 * Avoid mutating tenant state from listeners; prefer enriching
 * external systems.
 */
final class TenantResolved
{
    public function __construct(
        public readonly Model $tenant,
    ) {}
}

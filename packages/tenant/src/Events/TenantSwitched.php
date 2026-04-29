<?php

declare(strict_types=1);

namespace Arqel\Tenant\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Fired by `TenantSwitcherController` after an authenticated user
 * successfully switches tenant context. Carries the previous and
 * new tenant alongside the user that performed the switch — useful
 * for audit trails, cache invalidation and UI broadcasts.
 *
 * `from` is null when the user had no current tenant before the
 * switch (first selection / fresh login). `to` is always set,
 * since a successful switch implies a target tenant.
 */
final class TenantSwitched
{
    public function __construct(
        public readonly ?Model $from,
        public readonly Model $to,
        public readonly Authenticatable $user,
    ) {}
}

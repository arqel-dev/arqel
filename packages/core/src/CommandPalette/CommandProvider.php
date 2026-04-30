<?php

declare(strict_types=1);

namespace Arqel\Core\CommandPalette;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Contract for lazy command producers.
 *
 * Providers receive the current user (which may be null on guest
 * routes) and the active query string; they return a list of
 * {@see Command}s that should be considered for the response.
 *
 * Built-in providers (Navigation/Create/RecordSearch/Theme) land
 * in CMDPAL-002 once they can introspect the Resource API and
 * Policy gates.
 */
interface CommandProvider
{
    /**
     * @return array<int, Command>
     */
    public function provide(?Authenticatable $user, string $query): array;
}

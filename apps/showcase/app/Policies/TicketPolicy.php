<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

/**
 * Authorization policy for the {@see Ticket} model.
 *
 * Laravel 12 auto-discovers this policy by naming convention
 * (`App\Policies\{Model}Policy`), so it needs no manual registration. Note
 * that the mere existence of this class opts the Ticket model into policy
 * enforcement for EVERY ability — so the standard panel abilities
 * (`viewAny`, `view`, `create`, `update`, `delete`) must be declared here
 * too, mirroring the `arqel:resource --with-policy` scaffold, or the admin
 * resource pages would start returning 403.
 *
 * This single-panel showcase has no per-ticket ownership column, so any
 * authenticated admin-panel user is allowed. The point is that the gates
 * EXIST and are wired: a real application would tighten each method (and
 * especially `transition`) with ownership, team, or role/permission checks.
 */
final class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return true;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return true;
    }

    public function transition(User $user, Ticket $ticket): bool
    {
        return true;
    }
}

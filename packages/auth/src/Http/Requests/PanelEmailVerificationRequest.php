<?php

declare(strict_types=1);

namespace Arqel\Auth\Http\Requests;

use Arqel\Auth\Concerns\ResolvesPanelGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

/**
 * Email verification request scoped to the Arqel panel guard (#153).
 *
 * Laravel's {@see EmailVerificationRequest} resolves the user via a bare
 * `$this->user()` inside `authorize()` and `fulfill()`, which reads the
 * application's default guard. On a panel configured with a custom
 * `Panel::authGuard(...)` that is the wrong guard, so verification would
 * authorize/fulfill against a null user.
 *
 * Overriding `user()` to default to the resolved panel guard keeps
 * `authorize()`, `fulfill()` and the controller body all reading the
 * same (correct) user — the same invariant the #139 sweep established
 * for the rest of the bundled auth flow.
 */
final class PanelEmailVerificationRequest extends EmailVerificationRequest
{
    use ResolvesPanelGuard;

    /**
     * Resolve the authenticated user, defaulting to the panel guard
     * when no explicit guard is requested.
     */
    public function user($guard = null): ?Authenticatable
    {
        $user = parent::user($guard ?? $this->resolvePanelGuard());

        return $user instanceof Authenticatable ? $user : null;
    }
}

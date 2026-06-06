<?php

declare(strict_types=1);

namespace Arqel\Tenant\Resolvers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Resolves the tenant from a session key. Useful when the user
 * picks an active workspace from a switcher and the choice should
 * survive page navigation.
 */
class SessionResolver extends AbstractTenantResolver
{
    public function __construct(
        string $modelClass,
        string $identifierColumn = 'id',
        private readonly string $sessionKey = 'current_tenant_id',
    ) {
        parent::__construct($modelClass, $identifierColumn);
    }

    public function resolve(Request $request): ?Model
    {
        if (! $request->hasSession()) {
            return null;
        }

        $value = $request->session()->get($this->sessionKey);

        if (! is_scalar($value) || (string) $value === '') {
            return null;
        }

        return $this->findByIdentifier((string) $value);
    }

    /**
     * Persist the active tenant into the session key this resolver
     * reads from in `resolve()`. The inherited
     * `AbstractTenantResolver::switchTo()` writes a user DB column
     * (`current_tenant_id`), which this resolver never reads — so a
     * switch would be lost on the next request (#81). The signature
     * carries no Request, so we resolve the active session store from
     * the container.
     */
    public function switchTo(Authenticatable $user, Model $tenant): void
    {
        app('session')->put($this->sessionKey, $tenant->getKey());
    }
}

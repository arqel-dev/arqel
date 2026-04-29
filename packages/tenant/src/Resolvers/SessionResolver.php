<?php

declare(strict_types=1);

namespace Arqel\Tenant\Resolvers;

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
}

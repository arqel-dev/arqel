<?php

declare(strict_types=1);

use Arqel\Tenant\Resolvers\SessionResolver;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticated;
use Illuminate\Http\Request;

/**
 * Regression coverage for #81: `SessionResolver::resolve()` reads the
 * active tenant from a session key, but the resolver inherited
 * `AbstractTenantResolver::switchTo()`, which wrote a user DB column it
 * never reads. The switch was therefore visible only for the current
 * request and lost on the next one. The override must persist the
 * chosen tenant into the SAME session key `resolve()` reads from.
 */
function switchUser(): Authenticatable
{
    $user = new Authenticated;
    $user->id = 1;

    return $user;
}

/**
 * Build a SessionResolver whose `findByIdentifier()` returns a fixed
 * tenant, so `resolve()` exercises the real session read path without
 * touching the database.
 */
function switchableSessionResolver(string $sessionKey, ?Tenant $stub): SessionResolver
{
    return new class('Arqel\\Tenant\\Tests\\Fixtures\\Tenant', 'id', $sessionKey, $stub) extends SessionResolver
    {
        public function __construct(
            string $modelClass,
            string $identifierColumn,
            string $sessionKey,
            private readonly ?Tenant $stub,
        ) {
            parent::__construct($modelClass, $identifierColumn, $sessionKey);
        }

        protected function findByIdentifier(string $value): ?Model
        {
            if ($this->stub === null) {
                return null;
            }

            return (string) $this->stub->getKey() === $value ? $this->stub : null;
        }
    };
}

it('persists the switched tenant into the session so the next request resolves it', function (): void {
    $tenantB = new Tenant(['id' => 2, 'name' => 'Globex']);
    $resolver = switchableSessionResolver('current_tenant_id', $tenantB);

    // Switch (the request that performs the switch). The override must
    // write the session key via the active session store.
    $resolver->switchTo(switchUser(), $tenantB);

    // Simulate the NEXT request: a fresh Request bound to the same
    // (already-started) session store the resolver just wrote to.
    $next = Request::create('https://x.test/');
    $next->setLaravelSession(app('session')->driver());

    expect($resolver->resolve($next))->toBe($tenantB);
});

it('writes the chosen tenant key under the configured (non-default) session key', function (): void {
    $tenantB = new Tenant(['id' => 7, 'name' => 'Initech']);
    $resolver = switchableSessionResolver('workspace_id', $tenantB);

    $resolver->switchTo(switchUser(), $tenantB);

    // After the #131 fix `switchTo()` persists `identifierFor($tenant)`,
    // which returns the identifier-column value coerced to string.
    expect(app('session')->driver()->get('workspace_id'))->toBe('7');
});

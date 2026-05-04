<?php

declare(strict_types=1);

namespace Arqel\Tenant\Integrations;

use Arqel\Tenant\Contracts\TenantResolver;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LogicException;

/**
 * `TenantResolver` adapter that delegates to
 * [stancl/tenancy](https://tenancyforlaravel.com).
 *
 * Stancl owns the tenant lifecycle in their own middleware
 * (`InitializeTenancyByDomain`, `InitializeTenancyByPath`, etc.),
 * so this adapter is a thin pass-through: it asks Stancl's
 * `Tenancy` singleton for the currently-initialised tenant and
 * surfaces it through the Arqel `TenantManager`.
 *
 * **Usage:**
 *
 *  1. Install: `composer require stancl/tenancy`
 *  2. Set up Stancl per its docs (Tenant model, central domain,
 *     middleware in your routes).
 *  3. In `config/arqel.php`:
 *     ```
 *     'tenancy' => [
 *         'resolver' => StanclAdapter::class,
 *         'model'    => \App\Models\Tenant::class,
 *     ],
 *     ```
 *
 * The constructor does **not** type-hint `Stancl\Tenancy\Tenancy`
 * directly — `arqel-dev/tenant` cannot have a hard dep on Stancl. The
 * adapter resolves it from the container at call time and throws
 * a clear `LogicException` when Stancl is not installed.
 */
final class StanclAdapter implements TenantResolver
{
    public const string TENANCY_BINDING = 'Stancl\\Tenancy\\Tenancy';

    public function __construct(
        public readonly string $modelClass,
    ) {}

    public function resolve(Request $request): ?Model
    {
        $tenancy = $this->resolveTenancy();

        $tenant = $tenancy->tenant ?? null;

        return $tenant instanceof Model ? $tenant : null;
    }

    public function identifierFor(Model $tenant): string
    {
        if (method_exists($tenant, 'getTenantKey')) {
            $key = $tenant->getTenantKey();

            return is_scalar($key) ? (string) $key : '';
        }

        $key = $tenant->getKey();

        return is_scalar($key) ? (string) $key : '';
    }

    /**
     * Pulls the live `Stancl\Tenancy\Tenancy` instance from the
     * container. Throws `LogicException` when Stancl isn't
     * installed — the adapter is opt-in and the error message is
     * actionable for the developer.
     */
    private function resolveTenancy(): object
    {
        if (! class_exists(self::TENANCY_BINDING)) {
            throw new LogicException(
                'StanclAdapter requires the stancl/tenancy package — install it with `composer require stancl/tenancy`.',
            );
        }

        $container = Container::getInstance();

        if (! $container->bound(self::TENANCY_BINDING)) {
            throw new LogicException(
                'Stancl Tenancy is installed but not bound to the container — make sure TenancyServiceProvider is registered.',
            );
        }

        $tenancy = $container->make(self::TENANCY_BINDING);

        if (! is_object($tenancy)) {
            throw new LogicException(
                'Stancl Tenancy did not resolve to an object — container binding is corrupt.',
            );
        }

        return $tenancy;
    }
}

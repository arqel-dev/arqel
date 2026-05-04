<?php

declare(strict_types=1);

namespace Arqel\Tenant\Integrations;

use Arqel\Tenant\Contracts\TenantResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LogicException;

/**
 * `TenantResolver` adapter that delegates to
 * [spatie/laravel-multitenancy](https://spatie.be/docs/laravel-multitenancy).
 *
 * Spatie's tenant finder runs as part of its own middleware and
 * exposes the active tenant via `Spatie\Multitenancy\Models\Tenant
 * ::current()`. This adapter is a thin pass-through that surfaces
 * that state through Arqel's `TenantManager`.
 *
 * **Usage:**
 *
 *  1. Install: `composer require spatie/laravel-multitenancy`
 *  2. Set up Spatie per its docs (publish config, run migrations,
 *     configure tenant finder).
 *  3. In `config/arqel.php`:
 *     ```
 *     'tenancy' => [
 *         'resolver' => SpatieAdapter::class,
 *         'model'    => \App\Models\Tenant::class,
 *     ],
 *     ```
 *
 * The adapter does **not** import Spatie's classes — `arqel-dev/tenant`
 * has no hard dep on the package. We call the static `::current()`
 * via the model class-string passed at construction time, falling
 * back to the canonical Spatie model when the consumer leaves it
 * to convention.
 */
final class SpatieAdapter implements TenantResolver
{
    public const string SPATIE_TENANT_CLASS = 'Spatie\\Multitenancy\\Models\\Tenant';

    public function __construct(
        public readonly string $modelClass,
    ) {}

    public function resolve(Request $request): ?Model
    {
        $class = $this->resolveTenantClass();

        if (! method_exists($class, 'current')) {
            throw new LogicException(sprintf(
                'SpatieAdapter expected [%s] to expose a static `current()` method (Spatie\\Multitenancy\\Models\\Tenant convention).',
                $class,
            ));
        }

        $tenant = $class::current();

        return $tenant instanceof Model ? $tenant : null;
    }

    public function identifierFor(Model $tenant): string
    {
        $key = $tenant->getKey();

        return is_scalar($key) ? (string) $key : '';
    }

    /**
     * Returns the tenant model class to query. Honours the
     * developer's `modelClass` first (matches the convention used
     * by every other resolver in `arqel-dev/tenant`), falling back to
     * Spatie's canonical model when the caller passed an empty
     * string.
     *
     * Throws when neither the configured class nor Spatie's
     * canonical class are loaded — opt-in adapter, actionable
     * error.
     *
     * @return class-string
     */
    private function resolveTenantClass(): string
    {
        if ($this->modelClass !== '' && class_exists($this->modelClass)) {
            /** @var class-string $configured */
            $configured = $this->modelClass;

            return $configured;
        }

        if (class_exists(self::SPATIE_TENANT_CLASS)) {
            // class_exists() guards ensure the literal class-string
            // is loaded; PHPStan can't narrow a constant string into
            // a class-string at this site.
            // @phpstan-ignore return.type
            return self::SPATIE_TENANT_CLASS;
        }

        throw new LogicException(
            'SpatieAdapter requires the spatie/laravel-multitenancy package — install it with `composer require spatie/laravel-multitenancy`.',
        );
    }
}

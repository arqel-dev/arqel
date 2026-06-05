<?php

declare(strict_types=1);

namespace Arqel\Tenant;

use Arqel\Tenant\Commands\ScaffoldBillingCommand;
use Arqel\Tenant\Commands\ScaffoldProfileCommand;
use Arqel\Tenant\Commands\ScaffoldRegistrationCommand;
use Arqel\Tenant\Contracts\TenantResolver;
use Arqel\Tenant\Middleware\RequireTenantFeature;
use Arqel\Tenant\Middleware\ResolveTenantMiddleware;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router;
use ReflectionMethod;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered provider for `arqel-dev/tenant`.
 *
 * Binds:
 *   - `TenantResolver` to whatever class-string the
 *     `arqel.tenancy.resolver` config value points at, with the
 *     `arqel.tenancy.model` + `arqel.tenancy.identifier_column`
 *     values forwarded to the resolver constructor. Returns null
 *     when the config is absent so apps without tenancy still get
 *     a working `TenantManager`.
 *   - `TenantManager` as a singleton, wired with the resolver
 *     (when bound) and the application's event dispatcher.
 *
 * Apps that want a custom resolver bind `TenantResolver` before
 * the provider runs, or replace it via `$app->extend(...)`
 * afterwards.
 */
final class TenantServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('arqel-tenant')
            ->hasRoute('admin')
            ->hasCommand(ScaffoldRegistrationCommand::class)
            ->hasCommand(ScaffoldProfileCommand::class)
            ->hasCommand(ScaffoldBillingCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(TenantResolver::class, function (Container $app): ?TenantResolver {
            return $this->buildConfiguredResolver($app);
        });

        $this->app->singleton(TenantManager::class, function (Container $app): TenantManager {
            $resolver = null;
            if ($app->bound(TenantResolver::class)) {
                $candidate = $app->make(TenantResolver::class);
                if ($candidate instanceof TenantResolver) {
                    $resolver = $candidate;
                }
            }

            $events = $app->bound(Dispatcher::class) ? $app->make(Dispatcher::class) : null;

            return new TenantManager($resolver, $events);
        });

        $this->app->singleton(Theming\TenantThemeResolver::class);
    }

    public function packageBooted(): void
    {
        $router = $this->app->make(Router::class);

        if ($router instanceof Router) {
            $router->aliasMiddleware('arqel.tenant', ResolveTenantMiddleware::class);
            $router->aliasMiddleware('arqel.tenant.feature', RequireTenantFeature::class);
        }
    }

    /**
     * Instantiate the resolver class declared in
     * `config('arqel.tenancy.*')`. Returns null when the config
     * is missing or invalid — that's the no-tenancy default.
     */
    private function buildConfiguredResolver(Container $app): ?TenantResolver
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app->make('config');

        $resolverClass = $config->get('arqel.tenancy.resolver');
        $modelClass = $config->get('arqel.tenancy.model');

        if (! is_string($resolverClass) || ! is_string($modelClass)) {
            return null;
        }

        if (! class_exists($resolverClass) || ! is_subclass_of($resolverClass, TenantResolver::class)) {
            return null;
        }

        // Map config keys -> constructor parameter names. Build the
        // positional argument list by walking the constructor params in
        // order, using config when present, else the param's default.
        $configByParam = [
            'modelClass' => $modelClass,
            'identifierColumn' => $config->get('arqel.tenancy.identifier_column'),
            'relation' => $config->get('arqel.tenancy.relation'),
            'availableRelation' => $config->get('arqel.tenancy.available_relation'),
            'foreignKeyColumn' => $config->get('arqel.tenancy.foreign_key'),
        ];

        $constructor = new ReflectionMethod($resolverClass, '__construct');
        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            $value = $configByParam[$name] ?? null;

            if (is_string($value)) {
                $args[] = $value;

                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();

                continue;
            }

            // No config value and no default: skip it and let PHP raise a
            // precise ArgumentCountError naming the missing parameter when
            // the resolver is constructed (a truly required custom param
            // the framework cannot supply).
            continue;
        }

        /** @var TenantResolver $instance */
        $instance = new $resolverClass(...$args);

        return $instance;
    }
}

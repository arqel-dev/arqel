<?php

declare(strict_types=1);

namespace Arqel\Core;

use Arqel\Core\Cloud\CloudConfigurator;
use Arqel\Core\Cloud\CloudDetector;
use Arqel\Core\CommandPalette\CommandRegistry;
use Arqel\Core\CommandPalette\Providers\NavigationCommandProvider;
use Arqel\Core\CommandPalette\Providers\ThemeCommandProvider;
use Arqel\Core\Commands\InstallCommand;
use Arqel\Core\Commands\MakeResourceCommand;
use Arqel\Core\Commands\MakeUserCommand;
use Arqel\Core\Console\AuditCommand;
use Arqel\Core\Console\CloudInfoCommand;
use Arqel\Core\Console\DoctorCommand;
use Arqel\Core\Console\PulseInfoCommand;
use Arqel\Core\DevTools\DevToolsPayloadBuilder;
use Arqel\Core\DevTools\PolicyLogCollector;
use Arqel\Core\Http\Middleware\HandleArqelInertiaRequests;
use Arqel\Core\I18n\TranslationLoader;
use Arqel\Core\Panel\PanelRegistry;
use Arqel\Core\Pulse\PulseIntegration;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Core\Telemetry\AutoInstrumentation;
use Arqel\Core\Telemetry\MetricsCollector;
use Arqel\Core\Telemetry\PrometheusExporter;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Throwable;

final class ArqelServiceProvider extends PackageServiceProvider
{
    public const string FACADE_ACCESSOR = 'arqel';

    public function configurePackage(Package $package): void
    {
        $package
            ->name('arqel')
            ->hasConfigFile('arqel')
            ->hasViews('arqel')
            ->hasTranslations()
            ->hasRoute('admin')
            ->hasCommands([
                InstallCommand::class,
                MakeResourceCommand::class,
                MakeUserCommand::class,
                DoctorCommand::class,
                AuditCommand::class,
                CloudInfoCommand::class,
                PulseInfoCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Bind here so it is available before `packageBooted()` and
        // before any application provider that may register commands
        // or providers eagerly during boot.
        $this->app->singleton(CommandRegistry::class);
        $this->app->singleton(TranslationLoader::class);
        $this->app->singleton(CloudDetector::class);
        $this->app->singleton(CloudConfigurator::class);
        $this->app->singleton(PulseIntegration::class);

        // Telemetry — request-scoped collector + format-agnostic
        // exporters. Use `scoped` when available so values do not
        // leak across requests in long-lived workers (octane).
        if (method_exists($this->app, 'scoped')) {
            $this->app->scoped(MetricsCollector::class);
        } else {
            $this->app->singleton(MetricsCollector::class);
        }
        $this->app->singleton(PrometheusExporter::class);
        $this->app->singleton(AutoInstrumentation::class);
    }

    public function packageBooted(): void
    {
        $this->registerResourceRegistry();
        $this->registerPanelRegistry();
        $this->registerInertiaDataBuilder();
        $this->registerDevToolsServices();
        $this->registerFacade();

        // Apps register panels in their own ServiceProvider::boot, which
        // runs before ours by registration order. Defer the panel→registry
        // sync to `app->booted` so we always see the final list of panels
        // and resources, regardless of provider order.
        $this->app->booted(function (): void {
            $this->syncPanelResourcesIntoRegistry();
            $this->electDefaultCurrentPanel();
        });

        $this->registerResourceRoutes();
        $this->registerBuiltInCommandProviders();

        $this->app->booted(function (): void {
            $this->applyCloudAutoConfigure();
            $this->registerPulseIntegration();
            $this->registerTelemetryIntegration();
        });
    }

    /**
     * Wire telemetry: register the auto-instrumentation listeners
     * (when `arqel.telemetry.enabled`) and the Prometheus endpoint
     * route (when `arqel.telemetry.metrics_endpoint_enabled`).
     *
     * Both flags default to false — telemetry is opt-in. Wrapped
     * in `try/catch` so a misconfigured event/route never blocks
     * application boot.
     */
    protected function registerTelemetryIntegration(): void
    {
        try {
            if ((bool) config('arqel.telemetry.enabled', false)) {
                $instrumentation = $this->app->make(AutoInstrumentation::class);
                assert($instrumentation instanceof AutoInstrumentation);
                $events = $this->app->make(Dispatcher::class);
                assert($events instanceof Dispatcher);
                $instrumentation->subscribe($events);
            }

            // Route is always registered — the controller itself
            // returns 404 when `metrics_endpoint_enabled = false`.
            // This keeps the route table stable across config edits
            // and makes feature tests deterministic.
            $rawPath = config('arqel.telemetry.metrics_endpoint_path', '/admin/_metrics');
            $path = is_string($rawPath) && $rawPath !== '' ? $rawPath : '/admin/_metrics';

            if (! Route::has('arqel.telemetry.metrics')) {
                // Only `auth` is applied at the framework level — `web`
                // middleware (sessions/CSRF) is unnecessary for an
                // operations endpoint and complicates Testbench setup.
                Route::middleware(['auth'])
                    ->get($path, Http\Controllers\MetricsController::class)
                    ->name('arqel.telemetry.metrics');
            }
        } catch (Throwable $e) {
            Log::warning('Arqel telemetry integration failed to register', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Wire the Laravel Pulse integration (LCLOUD-003).
     *
     * The integration is no-op when `laravel/pulse` is not installed,
     * so this is safe to call unconditionally. Wrapped in try/catch
     * so a misbehaving Pulse install never blocks app boot.
     */
    protected function registerPulseIntegration(): void
    {
        try {
            $integration = $this->app->make(PulseIntegration::class);
            assert($integration instanceof PulseIntegration);
            $integration->register($this->app);
        } catch (Throwable $e) {
            Log::warning('Arqel Pulse integration failed to register', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Apply Laravel Cloud-friendly defaults at runtime (LCLOUD-002).
     *
     * Runs after the application has booted so all providers had a
     * chance to publish their config. The configurator is itself a
     * no-op when the host is not Laravel Cloud or when auto-configure
     * is disabled, so this is safe everywhere.
     */
    protected function applyCloudAutoConfigure(): void
    {
        try {
            if (! $this->app->resolved('config')) {
                return;
            }

            $configurator = $this->app->make(CloudConfigurator::class);
            assert($configurator instanceof CloudConfigurator);
            $changed = $configurator->configure();

            if ($changed !== [] && $this->app->environment('local')) {
                Log::info('Arqel Cloud auto-configure applied', ['changed' => $changed]);
            }
        } catch (Throwable) {
            // Never let cloud auto-configure block app boot.
        }
    }

    /**
     * Wire the built-in command palette providers (CMDPAL-002).
     *
     * - {@see NavigationCommandProvider} — emits one Command per
     *   registered Resource. Reads the registry on every request via
     *   `provide()`, so it picks up resources synced post-boot.
     * - {@see ThemeCommandProvider} — three static theme-switch
     *   commands.
     *
     * `CreateCommandProvider` and `RecordSearchProvider` are
     * deferred: both need policy authorisation + Resource model
     * traversal that lives in follow-up work.
     */
    protected function registerBuiltInCommandProviders(): void
    {
        $registry = $this->app->make(CommandRegistry::class);
        $registry->registerProvider($this->app->make(NavigationCommandProvider::class));
        $registry->registerProvider($this->app->make(ThemeCommandProvider::class));
    }

    protected function registerResourceRegistry(): void
    {
        $this->app->singleton(ResourceRegistry::class);
    }

    protected function registerPanelRegistry(): void
    {
        $this->app->singleton(PanelRegistry::class);
    }

    protected function registerInertiaDataBuilder(): void
    {
        $this->app->singleton(InertiaDataBuilder::class);
    }

    /**
     * Wire the DevTools services (DEVTOOLS-004).
     *
     * `PolicyLogCollector` is bound as a singleton so the request
     * lifecycle accumulates `Gate::after` events into one buffer. The
     * `Gate::after` listener is **only** registered when the app is
     * running in `local` environment — the policy log carries
     * argument values + stack traces that must never leak in
     * staging/production responses.
     */
    protected function registerDevToolsServices(): void
    {
        $app = $this->app;
        $app->singleton(PolicyLogCollector::class);
        $app->singleton(DevToolsPayloadBuilder::class, function () use ($app): DevToolsPayloadBuilder {
            $collector = $app->make(PolicyLogCollector::class);
            assert($collector instanceof PolicyLogCollector);

            return new DevToolsPayloadBuilder($app, $collector);
        });

        if (! $app->environment('local')) {
            return;
        }

        Gate::after(function (mixed $user, string $ability, ?bool $result, array $arguments) use ($app): void {
            try {
                $collector = $app->make(PolicyLogCollector::class);
                if (! $collector instanceof PolicyLogCollector) {
                    return;
                }
                /** @var array<int, mixed> $args */
                $args = array_values($arguments);
                $collector->record(
                    $ability,
                    $args,
                    (bool) $result,
                    debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8),
                );
            } catch (Throwable) {
                // Never let DevTools instrumentation break Gate checks.
            }
        });
    }

    protected function registerFacade(): void
    {
        $this->app->alias(PanelRegistry::class, self::FACADE_ACCESSOR);
    }

    /**
     * Register the polymorphic Resource routes under the panel
     * path. We pin them here (not via `hasRoute`) so we can apply
     * the panel's middleware stack and an explicit route prefix.
     */
    protected function registerResourceRoutes(): void
    {
        $registry = $this->app->make(PanelRegistry::class);

        $panel = $registry->getCurrent();
        $configPath = config('arqel.path', 'admin');
        $path = $panel?->getPath() ?? (is_string($configPath) ? $configPath : 'admin');
        $middleware = $panel?->getMiddleware() ?? ['web', HandleArqelInertiaRequests::class];

        if (! in_array(HandleArqelInertiaRequests::class, $middleware, true)) {
            $middleware[] = HandleArqelInertiaRequests::class;
        }

        Route::prefix($path)
            ->middleware($middleware)
            ->group(__DIR__.'/../routes/arqel.php');
    }

    /**
     * Walk every registered Panel and copy its `resources([...])`
     * declaration into the global `ResourceRegistry`. The
     * controller resolves slugs against the registry, not against
     * Panel state, so this sync is mandatory for `/admin/{slug}`
     * to ever resolve.
     *
     * Idempotent: `ResourceRegistry::register()` skips duplicates.
     */
    protected function syncPanelResourcesIntoRegistry(): void
    {
        $panelRegistry = $this->app->make(PanelRegistry::class);
        $resourceRegistry = $this->app->make(ResourceRegistry::class);

        foreach ($panelRegistry->all() as $panel) {
            foreach ($panel->getResources() as $resourceClass) {
                if (! is_string($resourceClass) || ! class_exists($resourceClass)) {
                    continue;
                }

                if ($resourceRegistry->has($resourceClass)) {
                    continue;
                }

                $resourceRegistry->register($resourceClass);
            }
        }
    }

    /**
     * Set the first declared panel as the current one when no
     * panel has been picked yet. Single-panel apps (the common
     * case) get this for free; multi-panel apps must override
     * via middleware that calls `PanelRegistry::setCurrent($id)`
     * based on path.
     */
    protected function electDefaultCurrentPanel(): void
    {
        $panelRegistry = $this->app->make(PanelRegistry::class);

        if ($panelRegistry->getCurrent() !== null) {
            return;
        }

        $panels = $panelRegistry->all();
        if ($panels === []) {
            return;
        }

        // First panel by insertion order; PanelRegistry::all()
        // returns a numerically-indexed list, so we read the id
        // from the Panel object itself.
        $first = $panels[0] ?? null;
        if ($first !== null) {
            $panelRegistry->setCurrent($first->id);
        }
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Core\Http\Middleware;

use Arqel\Core\DevTools\DevToolsPayloadBuilder;
use Arqel\Core\I18n\TranslationLoader;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Inertia middleware for the Arqel admin panel.
 *
 * Adds the standard `auth`/`panel`/`tenant`/`flash`/`translations`/
 * `arqel` shared props on top of whatever the host app already
 * registered. Closures are used for lazy props so we don't pay the
 * cost on partial reloads that don't request them.
 */
final class HandleArqelInertiaRequests extends Middleware
{
    /**
     * The Blade root view. Defaults to `arqel::app` (the Inertia
     * root template published by `arqel/core`) so apps don't need
     * to publish their own. Override `arqel.inertia.root_view`
     * config to point at an app-owned view (e.g. `'app'` after
     * publishing the package's `app.blade.php` or a custom one).
     *
     * @var string
     */
    protected $rootView = 'arqel::app';

    public function __construct()
    {
        $configured = config('arqel.inertia.root_view');
        if (is_string($configured) && $configured !== '') {
            $this->rootView = $configured;
        }
    }

    /**
     * Bust the Inertia asset version when Arqel itself updates so
     * the client picks up new bundles without a manual hard reload.
     */
    public function version(Request $request): ?string
    {
        /** @var string|null $base */
        $base = parent::version($request);
        $configVersion = config('arqel.version');
        $arqelVersion = is_string($configVersion) && $configVersion !== '' ? $configVersion : null;

        if ($arqelVersion === null) {
            return $base;
        }

        return ($base === null || $base === '') ? $arqelVersion : $base.'-'.$arqelVersion;
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $configVersion = config('arqel.version', '0.1.0');

        $user = $request->user();
        if (! $user instanceof Authenticatable) {
            $user = null;
        }

        return array_merge(parent::share($request), [
            'auth' => fn () => [
                'user' => $this->userPayload($user),
                'can' => $this->resolveAbilities($user),
            ],
            'panel' => fn () => $this->currentPanel(),
            'tenant' => fn () => $this->currentTenant($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'translations' => fn () => $this->translations(),
            'i18n' => fn () => $this->i18nPayload(),
            'arqel' => [
                'version' => is_string($configVersion) ? $configVersion : '0.1.0',
            ],
            '__devtools' => fn () => $this->devToolsPayload(),
        ]);
    }

    /**
     * Build the `__devtools` shared prop. Returns `null` outside the
     * `local` environment — see {@see DevToolsPayloadBuilder}.
     *
     * @return array<string, mixed>|null
     */
    private function devToolsPayload(): ?array
    {
        if (! app()->bound(DevToolsPayloadBuilder::class)) {
            return null;
        }

        $builder = app(DevToolsPayloadBuilder::class);

        if (! $builder instanceof DevToolsPayloadBuilder) {
            return null;
        }

        return $builder->build();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function userPayload(?Authenticatable $user): ?array
    {
        if ($user === null) {
            return null;
        }

        if (method_exists($user, 'only')) {
            $payload = $user->only(['id', 'name', 'email']);

            if (is_array($payload)) {
                $clean = [];
                foreach ($payload as $key => $value) {
                    $clean[(string) $key] = $value;
                }

                return $clean;
            }

            return null;
        }

        return ['id' => $user->getAuthIdentifier()];
    }

    /**
     * @return array<string, bool>
     */
    private function resolveAbilities(?Authenticatable $user): array
    {
        $registryClass = 'Arqel\\Auth\\AbilityRegistry';

        if (! class_exists($registryClass) || ! app()->bound($registryClass)) {
            return [];
        }

        $registry = app($registryClass);

        if (! is_object($registry) || ! method_exists($registry, 'resolveForUser')) {
            return [];
        }

        $abilities = $registry->resolveForUser($user);

        if (! is_array($abilities)) {
            return [];
        }

        $clean = [];
        foreach ($abilities as $key => $value) {
            $clean[(string) $key] = (bool) $value;
        }

        return $clean;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentPanel(): ?array
    {
        if (! app()->bound(PanelRegistry::class)) {
            return null;
        }

        $registry = app(PanelRegistry::class);
        $panel = $registry->getCurrent();

        if ($panel === null) {
            return null;
        }

        return [
            'id' => $panel->id,
            'path' => $panel->getPath(),
            'brand' => $panel->getBrand(),
        ];
    }

    private function currentTenant(Request $request): mixed
    {
        // Tenant scaffold for Phase 2 — stays null in Phase 1.
        return null;
    }

    /**
     * Build the `i18n` shared prop. Defensive — returns an empty
     * payload if the {@see TranslationLoader} is not bound (e.g.
     * tests that override the provider).
     *
     * @return array<string, mixed>
     */
    private function i18nPayload(): array
    {
        if (! app()->bound(TranslationLoader::class)) {
            return [
                'locale' => app()->getLocale(),
                'available' => ['en', 'pt_BR'],
                'translations' => [],
            ];
        }

        $loader = app(TranslationLoader::class);
        if (! $loader instanceof TranslationLoader) {
            return [
                'locale' => app()->getLocale(),
                'available' => ['en', 'pt_BR'],
                'translations' => [],
            ];
        }

        $locale = app()->getLocale();

        return [
            'locale' => $locale,
            'available' => $loader->availableLocales(),
            'translations' => $loader->loadForLocale($locale),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function translations(): array
    {
        $translator = app('translator');

        if (! is_object($translator) || ! method_exists($translator, 'getLoader')) {
            return [];
        }

        $loader = $translator->getLoader();
        $locale = app()->getLocale();

        if (! is_object($loader) || ! method_exists($loader, 'load')) {
            return [];
        }

        $messages = $loader->load($locale, '*', 'arqel');

        if (! is_array($messages)) {
            return [];
        }

        $clean = [];
        foreach ($messages as $key => $value) {
            $clean[(string) $key] = $value;
        }

        return $clean;
    }
}

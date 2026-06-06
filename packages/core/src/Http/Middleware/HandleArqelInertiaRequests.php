<?php

declare(strict_types=1);

namespace Arqel\Core\Http\Middleware;

use Arqel\Core\DevTools\DevToolsPayloadBuilder;
use Arqel\Core\I18n\TranslationLoader;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;
use Throwable;

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
     * root template published by `arqel-dev/core`) so apps don't need
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
            'panel' => fn () => $this->currentPanel($user),
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
    private function currentPanel(?Authenticatable $user): ?array
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
            'navigation' => $this->buildNavigation($panel, $user),
        ];
    }

    /**
     * Build the `panel.navigation` payload — one entry per Resource
     * registered with the current Panel. The Sidebar reads it via
     * `useNavigation()` (`@arqel-dev/hooks`) and renders grouped menu
     * items with icons + active highlighting.
     *
     * Items are emitted already grouped + ordered so the client's
     * first-encounter grouping ({@see Sidebar} `groupItems()`) yields the
     * right group order without any client-side knowledge of the explicit
     * list. Group order honors `Panel::navigationGroups([...])` when set;
     * groups absent from that list (and the ungrouped bucket) fall back
     * after the listed ones, ordered by their minimum item `navigationSort`
     * — which preserves today's pure per-item-sort behavior when no
     * explicit list is configured. Within each group, items keep their
     * per-item `navigationSort` ordering.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildNavigation(\Arqel\Core\Panel\Panel $panel, ?Authenticatable $user = null): array
    {
        $items = [];
        $request = request();
        $currentPath = $request instanceof Request ? '/'.ltrim($request->path(), '/') : null;
        $panelPath = '/'.trim($panel->getPath(), '/');

        foreach ($panel->getResources() as $resourceClass) {
            if (! class_exists($resourceClass) || ! is_subclass_of($resourceClass, \Arqel\Core\Resources\Resource::class)) {
                continue;
            }

            if ($this->resourceViewAnyDenied($resourceClass, $user)) {
                continue;
            }

            $slug = $resourceClass::getSlug();
            $label = $resourceClass::getPluralLabel();
            $url = rtrim($panelPath, '/').'/'.$slug;

            $items[] = [
                'label' => $label,
                'url' => $url,
                'icon' => $resourceClass::getNavigationIcon(),
                'group' => $resourceClass::getNavigationGroup(),
                'sort' => $resourceClass::getNavigationSort() ?? 0,
                'active' => $currentPath !== null && (
                    $currentPath === $url || str_starts_with($currentPath, $url.'/')
                ),
            ];
        }

        return $this->orderNavigationItems($items, $panel->getNavigationGroups());
    }

    /**
     * Decide whether a Resource's nav item must be hidden because the
     * current user is denied `viewAny` on its model.
     *
     * Mirrors {@see \Arqel\Core\Http\Controllers\ResourceController}'s
     * `authorize()`: only consult the Gate when a `viewAny` gate OR a
     * Policy for the model exists. When neither does (scaffold apps), the
     * item is always shown — denying nothing — so today's behavior holds.
     *
     * @param class-string<\Arqel\Core\Resources\Resource> $resourceClass
     */
    private function resourceViewAnyDenied(string $resourceClass, ?Authenticatable $user): bool
    {
        try {
            $modelClass = $resourceClass::getModel();
        } catch (Throwable) {
            // Resource without a declared model (scaffold/fixture) — there
            // is nothing to authorize against, so never hide it.
            return false;
        }

        if (! Gate::has('viewAny') && ! Gate::getPolicyFor($modelClass)) {
            return false;
        }

        return Gate::forUser($user)->denies('viewAny', $modelClass);
    }

    /**
     * Reorder the flat navigation list so groups appear in the order
     * defined by `$explicitGroups`, with unlisted groups (and the
     * ungrouped bucket) following — ordered by their minimum item sort —
     * and items within each group ordered by their per-item sort.
     *
     * @param array<int, array<string, mixed>> $items
     * @param array<int, string> $explicitGroups
     *
     * @return array<int, array<string, mixed>>
     */
    private function orderNavigationItems(array $items, array $explicitGroups): array
    {
        if ($items === []) {
            return [];
        }

        // Bucket items by group key (ungrouped items share the '' key) so
        // we can order the buckets independently of their members. The
        // insertion order of $buckets is the first-encounter group order.
        $buckets = [];
        foreach ($items as $item) {
            $group = $item['group'] ?? null;
            $key = is_string($group) ? $group : '';
            $buckets[$key][] = $item;
        }

        // Rank of each group in the explicit list (lower = earlier).
        $rank = [];
        foreach ($explicitGroups as $index => $group) {
            $rank[$group] = $index;
        }

        // Build a sortable list of group keys carrying their first-encounter
        // index and minimum per-item sort for deterministic tie-breaking.
        $groupKeys = array_keys($buckets);
        $sortable = [];
        foreach ($groupKeys as $encounter => $key) {
            $minSort = min(array_map(
                static function (array $i): int {
                    $sort = $i['sort'] ?? 0;

                    return is_numeric($sort) ? (int) $sort : 0;
                },
                $buckets[$key],
            ));

            $sortable[] = [
                'key' => $key,
                // Listed groups sort before unlisted ones, by list order;
                // unlisted groups fall back after, ordered by their minimum
                // item sort — equivalent to today's pure per-item-sort order
                // when no explicit list is configured.
                'listed' => array_key_exists($key, $rank) ? 0 : 1,
                'rank' => $rank[$key] ?? 0,
                'minSort' => $minSort,
                'encounter' => $encounter,
            ];
        }

        usort($sortable, static function (array $a, array $b): int {
            return [$a['listed'], $a['rank'], $a['minSort'], $a['encounter']]
                <=> [$b['listed'], $b['rank'], $b['minSort'], $b['encounter']];
        });

        $result = [];
        foreach ($sortable as $group) {
            $bucket = $buckets[$group['key']];
            usort($bucket, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);
            foreach ($bucket as $item) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Tenant context for the shared `tenant` Inertia prop.
     *
     * Resolves `Arqel\Tenant\TenantManager` via the container by class
     * name (duck-typed) so `arqel-dev/core` keeps no hard dependency on
     * `arqel-dev/tenant`. Returns null when the package is not installed.
     *
     * @return array{current: array<string, mixed>|null, available: array<int, array<string, mixed>>}|null
     */
    private function currentTenant(Request $request): mixed
    {
        $managerClass = 'Arqel\\Tenant\\TenantManager';
        if (! app()->bound($managerClass)) {
            return null;
        }

        $manager = app($managerClass);
        if (! method_exists($manager, 'current') || ! method_exists($manager, 'availableFor')) {
            return null;
        }

        $user = $request->user();

        return [
            'current' => $this->serialiseTenant($manager->current()),
            'available' => $user !== null
                ? array_values(array_filter(array_map(
                    fn ($tenant): ?array => $this->serialiseTenant($tenant),
                    $manager->availableFor($user),
                )))
                : [],
        ];
    }

    /**
     * @return array{id: mixed, name: mixed, slug: mixed, logo: mixed}|null
     */
    private function serialiseTenant(mixed $tenant): ?array
    {
        if (! $tenant instanceof \Illuminate\Database\Eloquent\Model) {
            return null;
        }

        return [
            'id' => $tenant->getKey(),
            'name' => $tenant->getAttribute('name'),
            'slug' => $tenant->getAttribute('slug'),
            'logo' => $tenant->getAttribute('logo'),
        ];
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

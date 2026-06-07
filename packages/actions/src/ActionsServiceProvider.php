<?php

declare(strict_types=1);

namespace Arqel\Actions;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Service provider for `arqel-dev/actions`.
 *
 * Action invocation is dispatched through `arqel-dev/core`'s resource
 * routes — never through standalone routes owned by this package:
 *
 *   - row view/edit/delete/restore → `/{panel}/{resource}/{id}…`
 *     (`arqel.resources.*`, see `Action::resolveStockUrl()`)
 *   - bulk actions                 → `/{panel}/{resource}/bulk/{action}`
 *     (`arqel.resources.bulk` → `ResourceController::bulkAction`, #48)
 *   - custom actions               → the explicit `->url(...)` the
 *     user declared on the Action
 *
 * Those core routes carry the panel/config middleware stack (web + auth
 * + tenant) resolved by `ArqelServiceProvider::registerResourceRoutes()`.
 *
 * A previous standalone `arqel.actions.*` route group (#174) duplicated
 * this dispatch surface but: (a) never registered in real apps — this
 * provider boots before core binds `PanelRegistry`, so the registration
 * guard short-circuited — and (b) resolved middleware from the Panel
 * only, ignoring `config('arqel.middleware')`, so it would have shipped
 * a `web`-only stack (no auth/tenant) while core's routes carry the full
 * stack. Wiring it up would have created an auth-divergent second path,
 * so it was removed instead. `ActionController` is retained: its methods
 * are exercised directly by `arqel-dev/core` and unit tests.
 */
final class ActionsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('arqel-actions');
    }
}

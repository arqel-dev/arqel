<?php

declare(strict_types=1);

/**
 * #174: the standalone `arqel.actions.*` routes were a dead, parallel
 * dispatch surface. They never registered (the provider booted before
 * core bound `PanelRegistry`) and, had they registered, they resolved
 * middleware from the Panel only — ignoring `config('arqel.middleware')`
 * — so they would have shipped a `web`-only stack while core's resource
 * routes carry the full auth/tenant stack. That divergence is an
 * authorization-bypass risk.
 *
 * The frontend dispatches every action through CORE routes:
 *   - row view/edit/delete/restore → `/admin/{slug}/{id}…`
 *   - bulk → `/admin/{slug}/bulk/{action}` (core `arqel.resources.bulk`)
 * (see `Action::resolveStockUrl()` + `ArqelIndexPage`). Nothing targets
 * `arqel.actions.*`. So the correct fix is to REMOVE the dead route
 * registration rather than wire up a second, auth-divergent surface.
 *
 * These tests pin that removal: no `arqel.actions.*` route exists, and
 * the dead routes file is gone.
 */
it('registers no standalone arqel.actions.* routes', function (): void {
    $actionRoutes = collect($this->app->make('router')->getRoutes()->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter(fn (?string $name) => $name !== null && str_starts_with($name, 'arqel.actions.'))
        ->values();

    expect($actionRoutes->all())->toBe([]);
});

it('removes the dead standalone action routes file', function (): void {
    expect(file_exists(__DIR__.'/../../routes/arqel-actions.php'))->toBeFalse();
});

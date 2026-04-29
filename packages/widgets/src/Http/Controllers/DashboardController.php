<?php

declare(strict_types=1);

namespace Arqel\Widgets\Http\Controllers;

use Arqel\Widgets\DashboardRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders an Arqel dashboard via Inertia.
 *
 * Two routes converge here:
 *   - `GET /admin` — defaults `$dashboardId` to `'main'`.
 *   - `GET /admin/dashboards/{dashboardId}` — explicit id.
 *
 * Resolution flow:
 *   1. Look up the `Dashboard` in `DashboardRegistry`.
 *   2. 404 if missing — multi-dashboard apps must register every id
 *      they expose; an unknown id is a routing/config error.
 *   3. Serialise via `Dashboard::resolve($user)` (canonical payload,
 *      filters by `canBeSeenBy`, sorts by widget `sort`).
 *   4. Pass through `?filters[...]` query parameters as
 *      `filterValues` so the React side can rehydrate filter state
 *      after deep-link refreshes.
 */
final class DashboardController
{
    public function show(Request $request, DashboardRegistry $registry, ?string $dashboardId = null): Response
    {
        $id = $dashboardId ?? 'main';

        $dashboard = $registry->get($id);
        abort_if($dashboard === null, 404);

        return Inertia::render('arqel::dashboard', [
            'dashboard' => $dashboard->resolve($request->user()),
            'filterValues' => $request->input('filters', []),
        ]);
    }
}

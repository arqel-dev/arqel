<?php

declare(strict_types=1);

namespace Arqel\Widgets\Http\Controllers;

use Arqel\Widgets\DashboardRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Endpoint for deferred + polling widget refetch.
 *
 * Serves `GET /admin/dashboards/{dashboardId}/widgets/{widgetId}/data`
 * (mounted by `WidgetsServiceProvider` under the active panel path
 * + middleware). Covers RF-W-04 (Polling) and RF-W-05 (Deferred).
 *
 * Flow:
 *   1. Resolve the dashboard via `DashboardRegistry::get` — 404 if
 *      unknown.
 *   2. Authorise via `Dashboard::canBeSeenBy($request->user())` — 403
 *      when the dashboard-level `canSee()` gate denies the user, so a
 *      restricted dashboard cannot be drained widget-by-widget.
 *   3. Resolve the widget via `Dashboard::findWidget` (class-string
 *      entries are container-instantiated) — 404 if unknown.
 *   4. Authorise via `Widget::canBeSeenBy($request->user())` — 403
 *      otherwise.
 *   5. Seed the dashboard's declared filter defaults under any
 *      request-supplied filters (request wins) before computing data,
 *      mirroring `Dashboard::resolve()` so a deferred widget's first
 *      lazy fetch matches the SSR payload.
 *   6. Return `{ data: <widget->data()> }` as JSON.
 */
final class WidgetDataController
{
    public function show(
        Request $request,
        DashboardRegistry $registry,
        string $dashboardId,
        string $widgetId,
    ): JsonResponse {
        $dashboard = $registry->get($dashboardId);
        if ($dashboard === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        $user = $request->user();
        $authUser = $user instanceof Authenticatable ? $user : null;
        abort_unless($dashboard->canBeSeenBy($authUser), HttpResponse::HTTP_FORBIDDEN);

        $widget = $dashboard->findWidget($widgetId);
        if ($widget === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        abort_unless($widget->canBeSeenBy($authUser), HttpResponse::HTTP_FORBIDDEN);

        // Merge the dashboard's declared filter defaults UNDER any
        // request-supplied filters so deferred fetches start from the
        // same baseline as the SSR `resolve()` path while still letting
        // client-driven filters win.
        $requestFilters = $request->input('filters');
        /** @var array<string, mixed> $requestFilters */
        $requestFilters = is_array($requestFilters) ? $requestFilters : [];
        $widget->filters(array_merge($dashboard->getFilters(), $requestFilters));

        return response()->json([
            'data' => $widget->data(),
        ]);
    }
}

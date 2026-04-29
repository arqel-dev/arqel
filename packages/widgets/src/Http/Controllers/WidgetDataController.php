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
 *   2. Resolve the widget via `Dashboard::findWidget` (class-string
 *      entries are container-instantiated) — 404 if unknown.
 *   3. Authorise via `Widget::canBeSeenBy($request->user())` — 403
 *      otherwise.
 *   4. Apply request-supplied filters (passthrough array) before
 *      computing data so the client can drive segmentation.
 *   5. Return `{ data: <widget->data()> }` as JSON.
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

        $widget = $dashboard->findWidget($widgetId);
        if ($widget === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        $user = $request->user();
        $authUser = $user instanceof Authenticatable ? $user : null;
        abort_unless($widget->canBeSeenBy($authUser), HttpResponse::HTTP_FORBIDDEN);

        $filters = $request->input('filters');
        if (is_array($filters)) {
            /** @var array<string, mixed> $filters */
            $widget->filters($filters);
        }

        return response()->json([
            'data' => $widget->data(),
        ]);
    }
}

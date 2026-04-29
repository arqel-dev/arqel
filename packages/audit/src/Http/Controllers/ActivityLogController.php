<?php

declare(strict_types=1);

namespace Arqel\Audit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\Activitylog\Models\Activity;

/**
 * Scaffold controller exposing a paginated index of activity log entries.
 *
 * Inertia rendering, scoping by subject, causer-aware filtering and
 * timeline UI integration land in AUDIT-002+ — for AUDIT-001 the
 * controller stays minimal (JSON payload) so downstream tickets can
 * iterate on the shape without churning the trait.
 */
final class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 50);
        $perPage = max(1, min($perPage, 200));

        $paginator = Activity::query()
            ->latest()
            ->paginate($perPage);

        $items = array_map(
            static fn (Activity $activity): array => [
                'id' => $activity->getKey(),
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'subject_type' => $activity->subject_type,
                'subject_id' => $activity->subject_id,
                'causer_type' => $activity->causer_type,
                'causer_id' => $activity->causer_id,
                'properties' => $activity->properties?->toArray() ?? [],
                'created_at' => $activity->created_at?->toIso8601String(),
            ],
            $paginator->items(),
        );

        return new JsonResponse([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}

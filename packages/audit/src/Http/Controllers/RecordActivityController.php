<?php

declare(strict_types=1);

namespace Arqel\Audit\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\Activitylog\Models\Activity;

/**
 * Per-record activity timeline endpoint (AUDIT-002 backend slice).
 *
 * Resolves the activity log entries scoped to a single Eloquent record
 * identified by `(subjectType, subjectId)`.
 *
 * **Subject type resolution is consumer responsibility.** This controller
 * accepts the model FQCN as `$subjectType` (matching what
 * `spatie/laravel-activitylog` stores in `activity_log.subject_type`).
 * Apps that want to expose pretty slugs (`'users'` → `App\Models\User`)
 * should add their own resolver layer (e.g. an Arqel resource registry)
 * and translate before hitting this endpoint.
 */
final class RecordActivityController extends Controller
{
    public function show(Request $request, string $subjectType, string|int $subjectId): JsonResponse
    {
        if ($subjectType === '' || ! class_exists($subjectType) || ! is_subclass_of($subjectType, Model::class)) {
            return new JsonResponse([
                'error' => 'invalid_subject_type',
                'message' => 'subjectType must be a fully-qualified Eloquent model class.',
            ], 400);
        }

        $perPage = $request->integer('per_page', 20);
        $perPage = max(1, min($perPage, 200));

        $paginator = Activity::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->with('causer')
            ->latest()
            ->paginate($perPage);

        $items = array_map(
            static function (Activity $activity): array {
                /** @var Model|null $causer */
                $causer = $activity->causer;

                $causerPayload = null;
                if ($causer !== null) {
                    $causerPayload = [
                        'id' => $causer->getKey(),
                        'type' => $activity->causer_type,
                        'name' => self::stringAttr($causer, 'name'),
                        'email' => self::stringAttr($causer, 'email'),
                    ];
                }

                return [
                    'id' => $activity->getKey(),
                    'log_name' => $activity->log_name,
                    'description' => $activity->description,
                    'event' => $activity->event,
                    'properties' => $activity->properties?->toArray() ?? [],
                    'causer' => $causerPayload,
                    'created_at' => $activity->created_at?->toIso8601String(),
                ];
            },
            $paginator->items(),
        );

        // Mirror Laravel's default paginator JSON shape (data + meta keys
        // alongside the standard pagination fields) while keeping our
        // serialised activity payloads as the `data` array.
        return new JsonResponse([
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    private static function stringAttr(Model $model, string $key): ?string
    {
        if (! array_key_exists($key, $model->getAttributes()) && ! $model->offsetExists($key)) {
            return null;
        }

        $value = $model->getAttribute($key);

        return is_string($value) ? $value : null;
    }
}

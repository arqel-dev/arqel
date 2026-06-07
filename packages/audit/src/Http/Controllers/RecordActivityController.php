<?php

declare(strict_types=1);

namespace Arqel\Audit\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
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
 *
 * SECURITY (issue #181): this endpoint accepts an attacker-controlled FQCN
 * as `$subjectType` (route `->where('subjectType', '.*')`) and returns that
 * record's activity (causer name/email, property diffs). We honor a `view`
 * authorization on the resolved subject model using the same convention as
 * `versioning/VersionHistoryController::deniesView()` (issue #91): the
 * check runs only when a named `view` gate OR a Policy for the model
 * exists; in scaffold mode (no gate, no policy) access stays open so the
 * showcase isn't broken. `Gate::has('view')` alone never consults
 * Policies, so we also probe `Gate::getPolicyFor()`.
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

        /** @var class-string<Model> $subjectType */
        $subject = $this->resolveSubject($subjectType, $subjectId);

        if ($this->deniesView($subject)) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
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

    /**
     * Resolve a subject model instance for authorization. We prefer the
     * persisted record so a Policy can inspect its attributes; when it no
     * longer exists we fall back to a fresh instance carrying the key, so
     * a class-level Policy still applies (the activity query itself stays
     * unchanged and may legitimately return an empty paginator).
     *
     * @param class-string<Model> $subjectType
     */
    private function resolveSubject(string $subjectType, string|int $subjectId): Model
    {
        $existing = $subjectType::query()->find($subjectId);

        if ($existing instanceof Model) {
            return $existing;
        }

        $fallback = new $subjectType;
        $fallback->setAttribute($fallback->getKeyName(), $subjectId);

        return $fallback;
    }

    /**
     * Decide whether `view` must be denied, honoring named gates AND
     * Policies. Mirrors `VersionHistoryController::deniesView()` (#91):
     * `Gate::has()` never consults Policies, so we also probe
     * `Gate::getPolicyFor()`. Only when neither exists do we default-allow
     * (scaffold mode).
     */
    private function deniesView(Model $subject): bool
    {
        if (! Gate::has('view') && Gate::getPolicyFor($subject) === null) {
            return false;
        }

        return Gate::denies('view', $subject);
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

<?php

declare(strict_types=1);

namespace Arqel\Audit\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
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
 * **Subject type resolution (issue #190).** `spatie/laravel-activitylog`
 * stores `subject_type` via the subject's `getMorphClass()` — which is the
 * morph-map ALIAS (`'post'`) when `Relation::enforceMorphMap()` is active,
 * and the FQCN otherwise. This controller therefore accepts `$subjectType`
 * as EITHER a model FQCN OR a registered morph alias, resolves it to the
 * backing model, and queries on `getMorphClass()` (the value actually
 * stored) — so drilling from the global feed (which serialises the stored
 * alias via {@see GlobalActivityLogController::serialiseActivity()}) into
 * this endpoint round-trips, and an FQCN query under a map still matches
 * the alias-keyed rows. Mirrors the morph-safe write+read pattern used by
 * `arqel-dev/workflow` (`PersistStateTransitionToHistory` +
 * `StateTransitionField`, issue #164).
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
        $modelClass = $this->resolveModelClass($subjectType);

        if ($modelClass === null) {
            return new JsonResponse([
                'error' => 'invalid_subject_type',
                'message' => 'subjectType must be a fully-qualified Eloquent model class or a registered morph alias.',
            ], 400);
        }

        $subject = $this->resolveSubject($modelClass, $subjectId);

        if ($this->deniesView($subject)) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        // Query on the value Spatie actually persists: `getMorphClass()` —
        // the morph-map alias under an active map, the FQCN otherwise.
        $morphValue = $subject->getMorphClass();

        $perPage = $request->integer('per_page', 20);
        $perPage = max(1, min($perPage, 200));

        $paginator = Activity::query()
            ->where('subject_type', $morphValue)
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
     * Resolve `{subjectType}` (a FQCN OR a morph alias) to the backing
     * Eloquent model class, or null when it is neither (issue #190).
     *
     * - A FQCN that is an Eloquent model resolves to itself (so without a
     *   map the behaviour is unchanged; with a map `getMorphClass()` on
     *   the resolved model later yields the stored alias).
     * - Otherwise we treat it as a morph alias and resolve via
     *   `Relation::getMorphedModel()`, which returns the FQCN registered in
     *   the active morph map (or null).
     *
     * @return class-string<Model>|null
     */
    private function resolveModelClass(string $subjectType): ?string
    {
        if ($subjectType === '') {
            return null;
        }

        if (class_exists($subjectType) && is_subclass_of($subjectType, Model::class)) {
            /** @var class-string<Model> $subjectType */
            return $subjectType;
        }

        $mapped = Relation::getMorphedModel($subjectType);

        if ($mapped !== null && is_subclass_of($mapped, Model::class)) {
            /** @var class-string<Model> $mapped */
            return $mapped;
        }

        return null;
    }

    /**
     * Resolve a subject model instance for authorization. We prefer the
     * persisted record so a Policy can inspect its attributes; when it no
     * longer exists we fall back to a fresh instance carrying the key, so
     * a class-level Policy still applies (the activity query itself stays
     * unchanged and may legitimately return an empty paginator).
     *
     * @param  class-string<Model>  $modelClass
     */
    private function resolveSubject(string $modelClass, string|int $subjectId): Model
    {
        $existing = $modelClass::query()->find($subjectId);

        if ($existing instanceof Model) {
            return $existing;
        }

        $fallback = new $modelClass;
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

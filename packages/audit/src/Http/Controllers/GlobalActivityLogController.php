<?php

declare(strict_types=1);

namespace Arqel\Audit\Http\Controllers;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Global filterable activity-log endpoint (AUDIT-003 — scoped slice).
 *
 * Returns the full Activity::query() paginator (latest first) with
 * optional filters applied via the `when()` builder. The response
 * mirrors {@see RecordActivityController}'s shape so a single React
 * client can render either feed.
 *
 * Scope note: AUDIT-003's full spec wires an
 * `ActivityLogResource extends Resource` into the panel nav. That
 * cross-package integration depends on `arqel-dev/core` Resource API +
 * `arqel-dev/table` Column/Filter API and is deferred to a follow-up
 * ticket. The config flags exposed here (`config('audit')`) give that
 * future Resource a stable url + nav metadata to consume.
 *
 * SECURITY (issue #181): the global log exposes every causer's
 * name/email plus full property diffs, so it must not be readable by any
 * authenticated user. We gate it on a global audit ability — a named
 * gate `view-audit-log` (`Gate::define`) OR a gate/Policy mapped to the
 * `Activity` model via the `viewAny` ability. The check is only enforced
 * when one of those exists; in scaffold mode (no gate, no policy) the log
 * stays open so the showcase isn't broken. Mirrors the deny-when-defined
 * convention used by `versioning/VersionHistoryController`.
 */
final class GlobalActivityLogController extends Controller
{
    private const ALLOWED_EVENTS = ['created', 'updated', 'deleted', 'restored'];

    /**
     * Named ability gating read access to the global audit log. Apps may
     * authorize via `Gate::define('view-audit-log', ...)` or by mapping a
     * `viewAny` gate/Policy onto the spatie `Activity` model.
     */
    private const ABILITY = 'view-audit-log';

    public function index(Request $request): JsonResponse
    {
        $this->authorizeGlobalLog();

        return $this->buildResponse($request);
    }

    /**
     * Enforce the global audit ability when (and only when) the app has
     * defined one. Default-allow in scaffold mode keeps the showcase open.
     */
    private function authorizeGlobalLog(): void
    {
        $hasNamedAbility = Gate::has(self::ABILITY);
        $hasActivityGate = Gate::has('viewAny') || Gate::getPolicyFor(Activity::class) !== null;

        if (! $hasNamedAbility && ! $hasActivityGate) {
            return;
        }

        $allowed = ($hasNamedAbility && Gate::allows(self::ABILITY))
            || ($hasActivityGate && Gate::allows('viewAny', Activity::class));

        if (! $allowed) {
            throw new HttpException(403, 'Forbidden');
        }
    }

    private function buildResponse(Request $request): JsonResponse
    {
        $logName = $this->stringQuery($request, 'log_name');
        $event = $this->stringQuery($request, 'event');
        $causerType = $this->stringQuery($request, 'causer_type');
        $causerIdRaw = $request->query('causer_id');
        $fromRaw = $this->stringQuery($request, 'from');
        $toRaw = $this->stringQuery($request, 'to');

        if ($event !== null && ! in_array($event, self::ALLOWED_EVENTS, true)) {
            return new JsonResponse([
                'error' => 'invalid_event',
                'message' => (string) __('arqel-audit::messages.invalid_event', [
                    'events' => implode(', ', self::ALLOWED_EVENTS),
                ]),
            ], 400);
        }

        $from = null;
        $to = null;

        try {
            if ($fromRaw !== null) {
                $from = Carbon::parse($fromRaw);
            }
            if ($toRaw !== null) {
                $to = Carbon::parse($toRaw);
            }
        } catch (Throwable) {
            return new JsonResponse([
                'error' => 'invalid_date',
                'message' => (string) __('arqel-audit::messages.invalid_date'),
            ], 400);
        }

        $causerId = null;
        if ($causerIdRaw !== null && $causerIdRaw !== '') {
            $causerId = is_numeric($causerIdRaw) ? (int) $causerIdRaw : (string) $causerIdRaw;
        }

        $perPage = $request->integer('per_page', 20);
        $perPage = max(1, min($perPage, 200));

        $query = Activity::query()
            ->with('causer')
            ->latest('created_at')
            ->when($logName !== null, fn ($q) => $q->where('log_name', $logName))
            ->when($event !== null, fn ($q) => $q->where('event', $event))
            ->when($causerType !== null, fn ($q) => $q->where('causer_type', $causerType))
            ->when($causerId !== null, fn ($q) => $q->where('causer_id', $causerId))
            ->when(
                $from instanceof CarbonInterface && $to instanceof CarbonInterface,
                fn ($q) => $q->whereBetween('created_at', [$from, $to]),
            )
            ->when(
                $from instanceof CarbonInterface && ! $to instanceof CarbonInterface,
                fn ($q) => $q->where('created_at', '>=', $from),
            )
            ->when(
                ! $from instanceof CarbonInterface && $to instanceof CarbonInterface,
                fn ($q) => $q->where('created_at', '<=', $to),
            );

        $paginator = $query->paginate($perPage);

        $items = array_map(
            fn (Activity $activity): array => $this->serialiseActivity($activity),
            $paginator->items(),
        );

        return new JsonResponse([
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialiseActivity(Activity $activity): array
    {
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
            'subject_type' => $activity->subject_type,
            'subject_id' => $activity->subject_id,
            'causer' => $causerPayload,
            'properties' => $activity->properties?->toArray() ?? [],
            'created_at' => $activity->created_at?->toIso8601String(),
        ];
    }

    private function stringQuery(Request $request, string $key): ?string
    {
        $value = $request->query($key);
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
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

<?php

declare(strict_types=1);

namespace Arqel\Audit\Http\Controllers;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
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
 * cross-package integration depends on `arqel/core` Resource API +
 * `arqel/table` Column/Filter API and is deferred to a follow-up
 * ticket. The config flags exposed here (`config('audit')`) give that
 * future Resource a stable url + nav metadata to consume.
 */
final class GlobalActivityLogController extends Controller
{
    private const ALLOWED_EVENTS = ['created', 'updated', 'deleted', 'restored'];

    public function index(Request $request): JsonResponse
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
                'message' => 'event must be one of: '.implode(', ', self::ALLOWED_EVENTS).'.',
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
                'message' => 'from/to must be ISO 8601 date strings.',
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

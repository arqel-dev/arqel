<?php

declare(strict_types=1);

namespace Arqel\Audit\Concerns;

use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity as SpatieLogsActivity;

/**
 * Convenience trait wrapping Spatie's `LogsActivity` with Arqel defaults:
 *
 * - logOnly($this->getAuditableAttributes())
 * - logOnlyDirty()
 * - dontSubmitEmptyLogs()
 * - useLogName(class_basename($this))
 *
 * Subclasses can override `getAuditableAttributes()` to customise the
 * whitelist of logged attributes (default: `$this->fillable ?? ['*']`).
 *
 * @phpstan-ignore trait.unused
 */
trait LogsActivity
{
    use SpatieLogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getAuditableAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName(class_basename($this));
    }

    /**
     * Attributes whitelisted for the activity log.
     *
     * Defaults to the model `$fillable` array, falling back to `['*']`
     * (log all dirty attributes) when the model has no fillable defined.
     *
     * @return array<int, string>
     */
    protected function getAuditableAttributes(): array
    {
        /** @var list<string> $fillable */
        $fillable = $this->getFillable();

        return $fillable === [] ? ['*'] : $fillable;
    }

    /**
     * Convenience helper: paginated activity log scoped to this record.
     *
     * Mirrors what `RecordActivityController::show` returns server-side,
     * for consumers that prefer to fetch from PHP without an HTTP round
     * trip (custom Inertia controllers, Livewire components, CLI tools,
     * tests). Spatie's trait already exposes `activities()` as a morph
     * relation; this just adds eager-load + ordering + pagination
     * defaults aligned with the controller.
     *
     * @return LengthAwarePaginator<int, Activity>
     */
    public function activityLog(int $perPage = 20): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, Activity> $paginator */
        $paginator = $this->activities()
            ->with('causer')
            ->latest()
            ->paginate($perPage);

        return $paginator;
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Audit\Concerns;

use Spatie\Activitylog\LogOptions;
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
}

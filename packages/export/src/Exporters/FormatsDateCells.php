<?php

declare(strict_types=1);

namespace Arqel\Export\Exporters;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Shared date-cell formatting for the CSV/XLSX/PDF exporters.
 *
 * A table `DateColumn` (arqel/table) serialises its rendering
 * intent through `props.mode` (`date`|`datetime`|`since`) and `props.format`
 * (a PHP date() format string), carried into the export column descriptor by
 * `ResourceController::serializeColumns`. Before #217 the exporters ignored
 * those props and hardcoded `Y-m-d`, silently dropping the time component of
 * `->dateTime()` columns and any custom `->date('d/m/Y')` format.
 *
 * This trait honours the column's serialised intent so export fidelity matches
 * the on-screen table:
 *   - `date` / `datetime` → `$value->format($props['format'])`
 *   - `since`             → a relative "2 days ago" string via Carbon's
 *                           diffForHumans(), matching the React `since` cell.
 *
 * Defaults mirror DateColumn's own defaults (`mode = date`, `format = Y-m-d`),
 * so a plain `->date()` column with no explicit props still exports `Y-m-d`
 * with no regression.
 */
trait FormatsDateCells
{
    /**
     * @param array<string, mixed> $column
     */
    private function formatDateCell(DateTimeInterface $value, array $column): string
    {
        $props = $column['props'] ?? [];
        $mode = is_array($props) ? ($props['mode'] ?? 'date') : 'date';

        if ($mode === 'since') {
            // Mirror the React `since` cell ("2 days ago"). The absolute value
            // is still recoverable from the source data when needed.
            return Carbon::instance($value)->diffForHumans();
        }

        $format = is_array($props) ? ($props['format'] ?? 'Y-m-d') : 'Y-m-d';

        return $value->format(is_string($format) ? $format : 'Y-m-d');
    }
}

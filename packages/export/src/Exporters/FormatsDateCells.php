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
            // Mirror the React `since` cell ("há 2 dias"). Bind Carbon to the
            // active application/request locale so the exported relative string
            // matches the now-localized on-screen cell instead of Carbon's
            // process-global default ('en'). The absolute value is still
            // recoverable from the source data when needed.
            return Carbon::instance($value)
                ->locale($this->activeLocale())
                ->diffForHumans();
        }

        $format = is_array($props) ? ($props['format'] ?? 'Y-m-d') : 'Y-m-d';

        // Bind to the active request locale and use translatedFormat() so textual
        // tokens (F/M/l/D) localize their month/day names, matching the on-screen
        // Intl-formatted cell instead of always emitting English. Numeric tokens
        // (Y/m/d/H/i/s) are unaffected, so a plain `Y-m-d` column is unchanged.
        return Carbon::instance($value)
            ->locale($this->activeLocale())
            ->translatedFormat(is_string($format) ? $format : 'Y-m-d');
    }

    /**
     * Localise a boolean cell to the active request/export locale.
     *
     * Before this, every exporter hardcoded the literal English 'Yes'/'No'
     * into the downloaded file regardless of `app()->getLocale()`. Routing
     * through `trans()` (resolved lazily at render time) makes a boolean
     * column export 'Sim'/'Não' under pt_BR, matching the on-screen cell.
     * The `arqel::messages.export.boolean_*` keys live in arqel/core, on
     * which this package already depends.
     */
    private function formatBooleanCell(mixed $value): string
    {
        $key = $value ? 'arqel::messages.export.boolean_yes' : 'arqel::messages.export.boolean_no';
        $fallback = $value ? 'Yes' : 'No';

        if (function_exists('app') && app()->bound('translator')) {
            $translated = trans($key);

            if (is_string($translated) && $translated !== $key) {
                return $translated;
            }
        }

        return $fallback;
    }

    /**
     * Resolve the active application locale for relative-date binding,
     * normalised to Carbon's underscore form (`pt_BR`). Falls back to
     * `en` when no translator is booted (bare unit context).
     */
    private function activeLocale(): string
    {
        if (function_exists('app') && app()->bound('translator')) {
            $resolved = app()->getLocale();

            if ($resolved !== '') {
                return str_replace('-', '_', $resolved);
            }
        }

        return 'en';
    }
}

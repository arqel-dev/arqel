/**
 * `<DashboardFilters>` — renders one control per dashboard `Filter`
 * payload (`Arqel\Widgets\Filters\*`). Two filter types are wired in
 * Phase 2 part-3: `select` (single dropdown) and `date_range` (from/to
 * date inputs). Unknown types are dropped silently — extension points
 * land in later slices.
 *
 * Controlled component: callers own `values` and react to `onChange`.
 */

import { cn } from '../utils/cn.js';

export interface SelectFilterPayload {
  name: string;
  type: 'select';
  label?: string | null;
  options?: Record<string, string> | Array<{ value: string; label: string }>;
  multiple?: boolean;
}

export interface DateRangeFilterPayload {
  name: string;
  type: 'date_range';
  label?: string | null;
}

export type DashboardFilterPayload =
  | SelectFilterPayload
  | DateRangeFilterPayload
  | { name: string; type: string; label?: string | null; [key: string]: unknown };

export interface DashboardFiltersProps {
  filters: DashboardFilterPayload[];
  values: Record<string, unknown>;
  onChange: (name: string, value: unknown) => void;
  className?: string | undefined;
}

export function DashboardFilters({ filters, values, onChange, className }: DashboardFiltersProps) {
  if (!filters || filters.length === 0) return null;

  return (
    <div className={cn('flex flex-wrap gap-2', className)} data-testid="dashboard-filters">
      {filters.map((filter) => (
        <FilterControl
          key={filter.name}
          filter={filter}
          value={values[filter.name]}
          onChange={(v) => onChange(filter.name, v)}
        />
      ))}
    </div>
  );
}

function FilterControl({
  filter,
  value,
  onChange,
}: {
  filter: DashboardFilterPayload;
  value: unknown;
  onChange: (value: unknown) => void;
}) {
  if (filter.type === 'select') {
    return (
      <SelectControl filter={filter as SelectFilterPayload} value={value} onChange={onChange} />
    );
  }
  if (filter.type === 'date_range') {
    return (
      <DateRangeControl
        filter={filter as DateRangeFilterPayload}
        value={value}
        onChange={onChange}
      />
    );
  }
  return null;
}

function controlClasses() {
  return cn(
    'h-9 rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-input)]',
    'bg-[var(--color-arqel-bg)] px-2 text-sm',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)]',
  );
}

function normaliseOptions(
  raw: SelectFilterPayload['options'],
): Array<{ value: string; label: string }> {
  if (!raw) return [];
  if (Array.isArray(raw)) {
    return raw.map((o) => ({ value: String(o.value), label: String(o.label ?? o.value) }));
  }
  return Object.entries(raw).map(([k, v]) => ({ value: String(k), label: String(v) }));
}

function SelectControl({
  filter,
  value,
  onChange,
}: {
  filter: SelectFilterPayload;
  value: unknown;
  onChange: (value: unknown) => void;
}) {
  const options = normaliseOptions(filter.options);
  return (
    <label className="flex flex-col gap-1 text-xs text-[var(--color-arqel-muted-fg)]">
      {filter.label ?? filter.name}
      <select
        aria-label={filter.label ?? filter.name}
        className={controlClasses()}
        value={value === undefined || value === null ? '' : String(value)}
        onChange={(e) => onChange(e.target.value === '' ? null : e.target.value)}
      >
        <option value="">All</option>
        {options.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>
    </label>
  );
}

function DateRangeControl({
  filter,
  value,
  onChange,
}: {
  filter: DateRangeFilterPayload;
  value: unknown;
  onChange: (value: unknown) => void;
}) {
  const range = (value && typeof value === 'object' ? (value as Record<string, unknown>) : {}) as {
    from?: unknown;
    to?: unknown;
  };
  const fromValue = typeof range.from === 'string' ? range.from : '';
  const toValue = typeof range.to === 'string' ? range.to : '';

  return (
    <fieldset className="flex flex-col gap-1 text-xs text-[var(--color-arqel-muted-fg)]">
      <legend>{filter.label ?? filter.name}</legend>
      <div className="flex items-center gap-1">
        <input
          type="date"
          aria-label={`${filter.label ?? filter.name} from`}
          className={controlClasses()}
          value={fromValue}
          onChange={(e) => onChange({ from: e.target.value || null, to: range.to ?? null })}
        />
        <span aria-hidden="true">–</span>
        <input
          type="date"
          aria-label={`${filter.label ?? filter.name} to`}
          className={controlClasses()}
          value={toValue}
          onChange={(e) => onChange({ from: range.from ?? null, to: e.target.value || null })}
        />
      </div>
    </fieldset>
  );
}

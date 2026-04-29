/**
 * `<TableFilters>` — renders one input per `FilterSchema` and emits
 * a single `onChange(name, value)` callback. Active values render as
 * dismissible chips so users can read state without re-opening menus.
 *
 * Phase 1 covers the four most-used filter types (select, multiSelect,
 * text, ternary). dateRange / scope land alongside FieldRenderer in
 * UI-004, where the date primitives ship.
 */

import type {
  FilterSchema,
  MultiSelectFilterSchema,
  SelectFilterSchema,
  TernaryFilterSchema,
  TextFilterSchema,
} from '@arqel/types/tables';
import { Button } from '../action/Button.js';
import { cn } from '../utils/cn.js';

export interface TableFiltersProps {
  filters: FilterSchema[];
  values: Record<string, unknown>;
  onChange: (name: string, value: unknown) => void;
  onClearAll?: (() => void) | undefined;
  className?: string | undefined;
}

export function TableFilters({
  filters,
  values,
  onChange,
  onClearAll,
  className,
}: TableFiltersProps) {
  if (filters.length === 0) return null;

  const activeCount = filters.reduce((acc, f) => {
    const v = values[f.name];
    return v !== undefined && v !== null && v !== '' ? acc + 1 : acc;
  }, 0);

  return (
    <fieldset className={cn('flex flex-wrap items-end gap-2 border-0 p-0', className)}>
      <legend className="sr-only">Filters</legend>
      {filters.map((filter) => (
        <FilterControl
          key={filter.name}
          filter={filter}
          value={values[filter.name]}
          onChange={(v) => onChange(filter.name, v)}
        />
      ))}
      {activeCount > 0 && onClearAll && (
        <Button variant="ghost" size="sm" onClick={onClearAll}>
          Clear filters ({activeCount})
        </Button>
      )}
    </fieldset>
  );
}

function FilterControl({
  filter,
  value,
  onChange,
}: {
  filter: FilterSchema;
  value: unknown;
  onChange: (value: unknown) => void;
}) {
  switch (filter.type) {
    case 'select':
      return <SelectFilter filter={filter} value={value} onChange={onChange} />;
    case 'multiSelect':
      return <MultiSelectFilter filter={filter} value={value} onChange={onChange} />;
    case 'text':
      return <TextFilter filter={filter} value={value} onChange={onChange} />;
    case 'ternary':
      return <TernaryFilter filter={filter} value={value} onChange={onChange} />;
    case 'dateRange':
    case 'scope':
      return null;
  }
}

function controlClasses() {
  return cn(
    'h-9 rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-input)]',
    'bg-[var(--color-arqel-bg)] px-3 text-sm',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)]',
  );
}

function SelectFilter({
  filter,
  value,
  onChange,
}: {
  filter: SelectFilterSchema;
  value: unknown;
  onChange: (value: unknown) => void;
}) {
  return (
    <label className="flex flex-col gap-1 text-xs text-[var(--color-arqel-muted-fg)]">
      {filter.label ?? filter.name}
      <select
        className={controlClasses()}
        value={value === undefined || value === null ? '' : String(value)}
        onChange={(e) => onChange(e.target.value === '' ? null : e.target.value)}
      >
        <option value="">All</option>
        {filter.props.options.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>
    </label>
  );
}

function MultiSelectFilter({
  filter,
  value,
  onChange,
}: {
  filter: MultiSelectFilterSchema;
  value: unknown;
  onChange: (value: unknown) => void;
}) {
  const arr = Array.isArray(value) ? (value as Array<string | number>) : [];
  return (
    <label className="flex flex-col gap-1 text-xs text-[var(--color-arqel-muted-fg)]">
      {filter.label ?? filter.name}
      <select
        multiple
        className={cn(controlClasses(), 'h-auto min-h-[2.25rem] py-1')}
        value={arr.map(String)}
        onChange={(e) => {
          const next = Array.from(e.target.selectedOptions, (o) => o.value);
          onChange(next.length === 0 ? null : next);
        }}
      >
        {filter.props.options.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>
    </label>
  );
}

function TextFilter({
  filter,
  value,
  onChange,
}: {
  filter: TextFilterSchema;
  value: unknown;
  onChange: (value: unknown) => void;
}) {
  return (
    <label className="flex flex-col gap-1 text-xs text-[var(--color-arqel-muted-fg)]">
      {filter.label ?? filter.name}
      <input
        type="text"
        className={controlClasses()}
        value={typeof value === 'string' ? value : ''}
        onChange={(e) => onChange(e.target.value === '' ? null : e.target.value)}
      />
    </label>
  );
}

function TernaryFilter({
  filter,
  value,
  onChange,
}: {
  filter: TernaryFilterSchema;
  value: unknown;
  onChange: (value: unknown) => void;
}) {
  const current = value === undefined || value === null ? 'all' : String(value);
  return (
    <label className="flex flex-col gap-1 text-xs text-[var(--color-arqel-muted-fg)]">
      {filter.label ?? filter.name}
      <select
        className={controlClasses()}
        value={current}
        onChange={(e) => {
          const v = e.target.value;
          onChange(v === 'all' ? null : v);
        }}
      >
        <option value="all">{filter.props.allLabel ?? 'All'}</option>
        <option value="true">{filter.props.trueLabel ?? 'Yes'}</option>
        <option value="false">{filter.props.falseLabel ?? 'No'}</option>
      </select>
    </label>
  );
}

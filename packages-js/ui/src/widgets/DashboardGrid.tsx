/**
 * `<DashboardGrid>` — top-level layout for an Arqel dashboard.
 *
 * Renders the dashboard heading + description, the filter bar (when the
 * dashboard declares filters), and a CSS Grid of widgets. Column count
 * accepts either a flat int or a responsive map (`{sm,md,lg,xl,2xl}`)
 * matching `Arqel\Widgets\Dashboard::columns()`. Each widget honours
 * `columnSpan` (defaults to 1) via `gridColumn: span N`.
 *
 * Filter values are controlled internally when `onFilterChange` is not
 * provided; supply that prop (and `filterValues`) to lift state out
 * (e.g., to push it to the URL through Inertia).
 */

import { type CSSProperties, useState } from 'react';
import { cn } from '../utils/cn.js';
import { type DashboardFilterPayload, DashboardFilters } from './DashboardFilters.js';
import { type WidgetPayload, WidgetRenderer } from './WidgetRenderer.js';

export interface DashboardPayload {
  id: string;
  label: string;
  path?: string | null;
  heading?: string | null;
  description?: string | null;
  widgets: WidgetPayload[];
  filters?: DashboardFilterPayload[] | Record<string, unknown> | null;
  columns: number | Partial<Record<'sm' | 'md' | 'lg' | 'xl' | '2xl', number>>;
}

export interface DashboardGridProps {
  dashboard: DashboardPayload;
  filterValues?: Record<string, unknown>;
  onFilterChange?: ((name: string, value: unknown) => void) | undefined;
  className?: string | undefined;
}

const BREAKPOINT_PREFIX: Record<string, string> = {
  sm: 'sm:',
  md: 'md:',
  lg: 'lg:',
  xl: 'xl:',
  '2xl': '2xl:',
};

export function DashboardGrid({
  dashboard,
  filterValues,
  onFilterChange,
  className,
}: DashboardGridProps) {
  const [internalValues, setInternalValues] = useState<Record<string, unknown>>(filterValues ?? {});
  const values = filterValues ?? internalValues;

  const handleChange = (name: string, value: unknown) => {
    if (onFilterChange) {
      onFilterChange(name, value);
    } else {
      setInternalValues((prev) => ({ ...prev, [name]: value }));
    }
  };

  const filterPayload = normaliseFilters(dashboard.filters);
  const heading = dashboard.heading ?? dashboard.label;

  return (
    <div className={cn('space-y-4', className)}>
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          {heading && <h1 className="text-xl font-semibold">{heading}</h1>}
          {dashboard.description && (
            <p className="text-sm text-muted-foreground">{dashboard.description}</p>
          )}
        </div>
        {filterPayload.length > 0 && (
          <DashboardFilters filters={filterPayload} values={values} onChange={handleChange} />
        )}
      </header>

      <div
        className={cn('grid gap-4', gridColsClass(dashboard.columns))}
        data-testid="dashboard-grid"
      >
        {dashboard.widgets.map((widget) => {
          const rawSpan = (widget as { columnSpan?: unknown }).columnSpan;
          const span = typeof rawSpan === 'number' ? rawSpan : 1;
          const style: CSSProperties = { gridColumn: `span ${span}` };
          return (
            <div key={widget.name} style={style} data-widget-slot={widget.name}>
              <WidgetRenderer widget={widget} filterValues={values} />
            </div>
          );
        })}
      </div>
    </div>
  );
}

function gridColsClass(columns: DashboardPayload['columns']): string {
  if (typeof columns === 'number') {
    return `grid-cols-${Math.max(1, Math.min(12, columns))}`;
  }
  const parts: string[] = [];
  // Base fallback when no `sm` provided.
  parts.push('grid-cols-1');
  for (const bp of ['sm', 'md', 'lg', 'xl', '2xl'] as const) {
    const value = columns[bp];
    if (typeof value === 'number') {
      parts.push(`${BREAKPOINT_PREFIX[bp]}grid-cols-${Math.max(1, Math.min(12, value))}`);
    }
  }
  return parts.join(' ');
}

function normaliseFilters(raw: DashboardPayload['filters']): DashboardFilterPayload[] {
  if (!raw) return [];
  if (Array.isArray(raw)) return raw;
  // Legacy `Record<string, mixed>` passthrough — render as a select-less
  // empty list so we don't crash. The declarative path is preferred.
  return [];
}

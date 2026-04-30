/**
 * `<WidgetWrapper>` — common chrome for dashboard widgets.
 *
 * Provides:
 * - Heading / description header.
 * - Loading skeleton (CLS-safe placeholder while deferred widgets fetch).
 * - Error state (`role="alert"`) with optional retry action.
 * - `aria-label`-ed `<section>` so widgets are individually navigable.
 *
 * Used by `<StatCard>` (and shortly `<ChartCard>` / `<TableCard>`) so the
 * outer card chrome is consistent across widget types.
 */

import type { ReactNode } from 'react';
import { cn } from '../utils/cn.js';

export interface WidgetWrapperProps {
  heading?: string | undefined;
  description?: string | undefined;
  loading?: boolean | undefined;
  error?: Error | null | undefined;
  onRetry?: (() => void) | undefined;
  /** Tailwind grid column span (number → `col-span-{n}`, or raw class). */
  columnSpan?: number | string | undefined;
  className?: string | undefined;
  children: ReactNode;
}

function resolveColumnSpan(columnSpan?: number | string): string | undefined {
  if (columnSpan === undefined) return undefined;
  if (typeof columnSpan === 'number') return `col-span-${columnSpan}`;
  return columnSpan;
}

export function WidgetWrapper({
  heading,
  description,
  loading = false,
  error = null,
  onRetry,
  columnSpan,
  className,
  children,
}: WidgetWrapperProps) {
  const baseClass = cn(
    'rounded border bg-background p-4',
    resolveColumnSpan(columnSpan),
    className,
  );

  if (loading) {
    return (
      <section
        aria-label={heading}
        aria-busy="true"
        className={baseClass}
        data-widget-state="loading"
      >
        <div className="animate-pulse h-32 bg-muted rounded" />
      </section>
    );
  }

  if (error) {
    return (
      <section aria-label={heading} className={baseClass} data-widget-state="error">
        <div role="alert" className="flex flex-col gap-2 text-sm text-destructive">
          <p>{error.message}</p>
          {onRetry && (
            <button
              type="button"
              onClick={onRetry}
              className="self-start rounded border px-2 py-1 text-xs text-foreground hover:bg-muted"
            >
              Retry
            </button>
          )}
        </div>
      </section>
    );
  }

  return (
    <section aria-label={heading} className={baseClass} data-widget-state="ready">
      {heading && <h2 className="text-sm font-medium text-muted-foreground">{heading}</h2>}
      {description && <p className="mt-1 text-xs text-muted-foreground">{description}</p>}
      <div className={cn(heading || description ? 'mt-3' : undefined)}>{children}</div>
    </section>
  );
}

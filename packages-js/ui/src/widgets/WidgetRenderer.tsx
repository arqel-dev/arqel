/**
 * `<WidgetRenderer>` — dispatches widget payloads to the right card
 * by `widget.type` (`stat`, `chart`, `table`). Unknown / `custom` types
 * surface a `role="alert"` so a misconfigured registry never silently
 * blanks the dashboard.
 *
 * Two server interactions live here:
 *   1. **Polling** — when `widget.poll > 0`, re-fetch the widget data
 *      endpoint every N seconds and replace the inline payload.
 *   2. **Deferred** — when `widget.deferred === true` and `data` is
 *      `null`, fetch once on mount.
 *
 * Both call the canonical endpoint
 * `/admin/dashboards/{dashboardId}/widgets/{widgetId}/data` so the
 * payload shape stays identical to the SSR / Inertia path.
 */

import { useEffect, useRef, useState } from 'react';
import { ChartCard } from './ChartCard.js';
import { StatCard } from './StatCard.js';
import { TableCard } from './TableCard.js';

export interface WidgetPayload {
  name: string;
  type: string;
  component?: string;
  data?: unknown;
  poll?: number | null;
  deferred?: boolean;
  dashboardId?: string;
  widgetId?: string;
  [key: string]: unknown;
}

export interface WidgetRendererProps {
  widget: WidgetPayload;
  filterValues?: Record<string, unknown>;
  fetcher?: ((url: string) => Promise<unknown>) | undefined;
}

export function WidgetRenderer({ widget, filterValues, fetcher }: WidgetRendererProps) {
  const [data, setData] = useState<unknown>(widget.data ?? null);
  const filterRef = useRef(filterValues);
  filterRef.current = filterValues;

  const dashboardId = widget.dashboardId;
  const widgetId = widget.widgetId ?? widget.name;
  const poll = typeof widget.poll === 'number' ? widget.poll : 0;
  const deferred = widget.deferred === true;
  // Capture the initial-payload presence in a ref so the effect doesn't
  // need to take `widget.data` as a dep (we only consult it once on mount).
  const hasInlineDataRef = useRef(widget.data !== null && widget.data !== undefined);

  useEffect(() => {
    if (!dashboardId || !widgetId) return undefined;

    const url = `/admin/dashboards/${dashboardId}/widgets/${widgetId}/data`;
    const doFetch = async () => {
      try {
        const result = fetcher
          ? await fetcher(url)
          : await fetch(url, { headers: { Accept: 'application/json' } }).then((r) => r.json());
        setData(result);
      } catch {
        // Swallow — the inline payload (or the previous successful poll) stays.
      }
    };

    let interval: ReturnType<typeof setInterval> | undefined;

    if (deferred && !hasInlineDataRef.current) {
      void doFetch();
    }

    if (poll > 0) {
      interval = setInterval(() => {
        void doFetch();
      }, poll * 1000);
    }

    return () => {
      if (interval) clearInterval(interval);
    };
  }, [dashboardId, widgetId, poll, deferred, fetcher]);

  const merged = mergeData(widget, data);

  switch (widget.type) {
    case 'stat':
      return <StatCard widget={merged as never} />;
    case 'chart':
      return <ChartCard widget={merged as never} />;
    case 'table':
      return <TableCard widget={merged as never} />;
    default:
      return (
        <div role="alert" className="text-sm text-red-600">
          Widget type {widget.type} not registered
        </div>
      );
  }
}

function mergeData(widget: WidgetPayload, data: unknown): Record<string, unknown> {
  if (data && typeof data === 'object' && !Array.isArray(data)) {
    return { ...widget, ...(data as Record<string, unknown>) };
  }
  return { ...widget, data };
}

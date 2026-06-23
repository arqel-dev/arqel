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
 * `/admin/dashboards/{dashboardId}/widgets/{widgetId}/data`. The
 * controller wraps the payload under `{ data: ... }`, so `doFetch`
 * unwraps that envelope before storing — the resulting shape matches
 * the SSR / Inertia path, which already seeds from `widget.data`. The
 * active `DashboardFilters` selection is forwarded as `filters[k]=v`
 * query params so deferred / poll refetches stay segmented.
 */

import { useArqelTranslations } from '@arqel-dev/react/utils';
import { useEffect, useRef, useState } from 'react';
import { ChartCard } from './ChartCard.js';
import { StatCard } from './StatCard.js';
import { TableCard } from './TableCard.js';

export interface WidgetPayload {
  id?: string;
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
  const t = useArqelTranslations();
  const [data, setData] = useState<unknown>(widget.data ?? null);
  const filterRef = useRef(filterValues);
  filterRef.current = filterValues;

  const dashboardId = widget.dashboardId;
  // `Dashboard::findWidget` matches on `id()` (`<type>:<name>`), so the refetch
  // must target that — `Dashboard::resolve` now injects `widgetId`. Fall back to
  // the serialized `id` (also the full `id()`) before the bare `name`.
  const widgetId = widget.widgetId ?? widget.id ?? widget.name;
  const poll = typeof widget.poll === 'number' ? widget.poll : 0;
  const deferred = widget.deferred === true;
  // Capture the initial-payload presence in a ref so the effect doesn't
  // need to take `widget.data` as a dep (we only consult it once on mount).
  const hasInlineDataRef = useRef(widget.data !== null && widget.data !== undefined);

  useEffect(() => {
    if (!dashboardId || !widgetId) return undefined;

    const baseUrl = `/admin/dashboards/${dashboardId}/widgets/${widgetId}/data`;
    const doFetch = async () => {
      try {
        // Build the URL with the *live* filters so deferred / poll refetches
        // honour the dashboard's `DashboardFilters` selection. The server
        // reads `$request->input('filters')` as an array, so each entry is
        // serialised as `filters[key]=value`.
        const url = withFilters(baseUrl, filterRef.current);
        const body = fetcher
          ? await fetcher(url)
          : await fetch(url, { headers: { Accept: 'application/json' } }).then((r) => r.json());
        // Unwrap the controller envelope (`{ data: ... }`) so the fetch
        // payload shape matches the SSR / Inertia path, which already seeds
        // from the unwrapped `widget.data`. Defensive: accept bare payloads.
        const result =
          body && typeof body === 'object' && 'data' in (body as Record<string, unknown>)
            ? (body as Record<string, unknown>)['data']
            : body;
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
          {t('widgets.unknown_type', `Widget type ${widget.type} not registered`, {
            type: widget.type,
          })}
        </div>
      );
  }
}

/**
 * Append non-empty filter values as query params so the Laravel controller
 * resolves them via `$request->input('filters')` as a nested array. The shape
 * must mirror what the SSR / deep-link path produces (`router.get(path,
 * { filters })`, qs bracket notation) so a deferred / polling widget's lazy
 * refetch agrees with its SSR payload:
 *   - **array** → `filters[key][]=el` per element (multi-`SelectControl`, #189)
 *   - **plain object** → `filters[key][subKey]=val` per leaf (`DateRangeControl`
 *     emits `{from,to}` → `filters[range][from]=..&filters[range][to]=..`)
 *   - **scalar** → `filters[key]=value`
 * `null` / `undefined` / empty-string values (and leaves) are skipped to keep
 * the URL clean and match the prior scalar behaviour.
 */
function withFilters(baseUrl: string, filters: Record<string, unknown> | undefined): string {
  if (!filters) return baseUrl;

  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    appendFilterValue(params, `filters[${key}]`, value);
  }

  const query = params.toString();
  return query ? `${baseUrl}?${query}` : baseUrl;
}

/**
 * Serialize a single filter value under `prefix`, recursing one level for the
 * array / plain-object shapes the dashboard filters emit (scalar | string[] |
 * `{from,to}`). `Array.isArray` is checked before the plain-object branch since
 * arrays are also objects. Empty leaves are skipped, never appended.
 */
function appendFilterValue(params: URLSearchParams, prefix: string, value: unknown): void {
  if (value === null || value === undefined || value === '') return;

  if (Array.isArray(value)) {
    for (const el of value) {
      appendFilterValue(params, `${prefix}[]`, el);
    }
    return;
  }

  if (typeof value === 'object') {
    for (const [subKey, subVal] of Object.entries(value as Record<string, unknown>)) {
      appendFilterValue(params, `${prefix}[${subKey}]`, subVal);
    }
    return;
  }

  params.append(prefix, String(value));
}

function mergeData(widget: WidgetPayload, data: unknown): Record<string, unknown> {
  if (data && typeof data === 'object' && !Array.isArray(data)) {
    return { ...widget, ...(data as Record<string, unknown>) };
  }
  return { ...widget, data };
}

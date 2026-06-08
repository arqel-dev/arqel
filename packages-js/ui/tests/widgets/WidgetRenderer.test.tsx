import { act, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { type WidgetPayload, WidgetRenderer } from '../../src/widgets/WidgetRenderer.js';

describe('WidgetRenderer dispatch', () => {
  it('dispatches stat type to StatCard', () => {
    const widget: WidgetPayload = {
      name: 'kpi',
      type: 'stat',
      heading: 'KPI',
      value: 7,
    };
    render(<WidgetRenderer widget={widget} />);
    expect(screen.getByText('7')).toBeInTheDocument();
  });

  it('dispatches chart type to ChartCard', () => {
    const widget: WidgetPayload = {
      name: 'sales',
      type: 'chart',
      heading: 'Sales',
      chartType: 'line',
      chartData: { labels: [], datasets: [] },
      height: 300,
      showLegend: true,
      showGrid: true,
    };
    render(<WidgetRenderer widget={widget} />);
    // ChartCard wraps in WidgetWrapper; the section's aria-label reflects the widget heading.
    expect(screen.getByRole('region', { name: 'Sales' })).toBeInTheDocument();
  });

  it('dispatches table type to TableCard', () => {
    const widget: WidgetPayload = {
      name: 'orders',
      type: 'table',
      heading: 'Orders',
      columns: [{ name: 'id', label: 'ID' }],
      records: [{ id: 1 }],
      limit: 10,
    };
    render(<WidgetRenderer widget={widget} />);
    expect(screen.getByRole('table')).toBeInTheDocument();
    expect(screen.getByText('ID')).toBeInTheDocument();
  });

  it('renders alert for unknown widget type', () => {
    const widget: WidgetPayload = { name: 'mystery', type: 'custom' };
    render(<WidgetRenderer widget={widget} />);
    expect(screen.getByRole('alert')).toHaveTextContent('Widget type custom not registered');
  });
});

describe('WidgetRenderer polling', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });
  afterEach(() => {
    vi.useRealTimers();
  });

  it('polls the widget data endpoint and renders the unwrapped envelope', async () => {
    const fetcher = vi
      .fn()
      .mockResolvedValueOnce({ data: { value: 10, color: 'primary' } })
      .mockResolvedValueOnce({ data: { value: 20, color: 'primary' } });
    const widget: WidgetPayload = {
      name: 'kpi',
      type: 'stat',
      heading: 'KPI',
      value: 0,
      color: 'primary',
      poll: 5,
      dashboardId: 'overview',
      widgetId: 'kpi',
    };
    render(<WidgetRenderer widget={widget} fetcher={fetcher} />);

    await act(async () => {
      vi.advanceTimersByTime(5000);
    });
    await act(async () => {
      await Promise.resolve();
    });
    expect(fetcher).toHaveBeenCalledWith('/admin/dashboards/overview/widgets/kpi/data');
    expect(fetcher).toHaveBeenCalledTimes(1);
    // Unwrapped envelope → StatCard renders the top-level value, not blank.
    expect(screen.getByText('10')).toBeInTheDocument();
  });

  it('fetches deferred widgets on mount and renders the unwrapped value', async () => {
    // The controller wraps the payload under `data`; the renderer must unwrap
    // it so StatCard reads the top-level `value`/`color` (issue #68 defect A).
    const fetcher = vi.fn().mockResolvedValue({ data: { value: 99, color: 'success' } });
    const widget: WidgetPayload = {
      name: 'kpi',
      type: 'stat',
      heading: 'KPI',
      value: null,
      color: 'primary',
      data: null,
      deferred: true,
      dashboardId: 'overview',
      widgetId: 'kpi',
    };
    render(<WidgetRenderer widget={widget} fetcher={fetcher} />);
    await act(async () => {
      await Promise.resolve();
    });
    expect(fetcher).toHaveBeenCalledTimes(1);
    expect(screen.getByText('99')).toBeInTheDocument();
    expect(screen.queryByText('—')).not.toBeInTheDocument();
  });

  it('fetches a deferred widget using the REAL PHP payload shape (issue #83 A)', async () => {
    // Mirror exactly what `Dashboard::resolve()` + `Widget::toArray()` now emit:
    // `data: null` + `deferred: true` + injected `dashboardId` + `widgetId` set
    // to the full `id()` (`<type>:<name>`, NOT the bare name) + `poll` key.
    // Before the fix PHP omitted dashboardId/widgetId and emitted
    // `pollingInterval`, so the renderer's guard returned early → no fetch.
    const fetcher = vi.fn().mockResolvedValue({
      data: { value: 99, color: 'success', statDescription: '+12% vs last week' },
    });
    const widget: WidgetPayload = {
      id: 'stat:revenue',
      name: 'revenue',
      type: 'stat',
      heading: 'Revenue',
      description: 'Monthly recurring',
      value: null,
      color: 'primary',
      poll: null,
      deferred: true,
      data: null,
      dashboardId: 'overview',
      widgetId: 'stat:revenue',
    };
    render(<WidgetRenderer widget={widget} fetcher={fetcher} />);

    await act(async () => {
      await Promise.resolve();
    });

    expect(fetcher).toHaveBeenCalledTimes(1);
    // Refetch must target the full `id()` so `Dashboard::findWidget` matches
    // (a bare `name` would 404).
    expect(fetcher).toHaveBeenCalledWith('/admin/dashboards/overview/widgets/stat:revenue/data');
    expect(screen.getByText('99')).toBeInTheDocument();
    // The fetched `statDescription` renders, and the chrome `description`
    // subtitle survives (the merged payload keeps both keys distinct).
    expect(screen.getByText('+12% vs last week')).toBeInTheDocument();
    expect(screen.getByText('Monthly recurring')).toBeInTheDocument();
  });

  it('appends the live dashboard filters to the fetch URL (issue #68 defect B)', async () => {
    const fetcher = vi.fn().mockResolvedValue({ data: { value: 5, color: 'primary' } });
    const widget: WidgetPayload = {
      name: 'kpi',
      type: 'stat',
      heading: 'KPI',
      value: null,
      color: 'primary',
      data: null,
      deferred: true,
      dashboardId: 'overview',
      widgetId: 'kpi',
    };
    render(
      <WidgetRenderer widget={widget} filterValues={{ status: 'published' }} fetcher={fetcher} />,
    );
    await act(async () => {
      await Promise.resolve();
    });
    expect(fetcher).toHaveBeenCalledTimes(1);
    const calledUrl = fetcher.mock.calls[0]?.[0] as string;
    expect(calledUrl).toContain('/admin/dashboards/overview/widgets/kpi/data');
    // Server reads `$request->input('filters')` as an array → `filters[k]=v`.
    expect(calledUrl).toContain(`filters%5Bstatus%5D=published`);
  });

  it('serializes ARRAY filter values as repeated `filters[k][]` params (issue #199)', async () => {
    // A multi-select filter (array since #189) must serialize as
    // `filters[k][]=a&filters[k][]=b` — NOT `filters[k]=a,b` (`String(['a','b'])`),
    // which the server would read as a single scalar string.
    const fetcher = vi.fn().mockResolvedValue({ data: { value: 5, color: 'primary' } });
    const widget: WidgetPayload = {
      name: 'kpi',
      type: 'stat',
      heading: 'KPI',
      value: null,
      color: 'primary',
      data: null,
      deferred: true,
      dashboardId: 'overview',
      widgetId: 'kpi',
    };
    render(
      <WidgetRenderer widget={widget} filterValues={{ tags: ['a', 'b'] }} fetcher={fetcher} />,
    );
    await act(async () => {
      await Promise.resolve();
    });
    const calledUrl = fetcher.mock.calls[0]?.[0] as string;
    // `filters[tags][]=a&filters[tags][]=b` URL-encoded.
    expect(calledUrl).toContain('filters%5Btags%5D%5B%5D=a');
    expect(calledUrl).toContain('filters%5Btags%5D%5B%5D=b');
    // Regression guard against the flat `String([...])` join.
    expect(calledUrl).not.toContain('filters%5Btags%5D=a%2Cb');
  });

  it('serializes OBJECT (date-range) filter values as nested `filters[k][subKey]` params (issue #199)', async () => {
    // `DateRangeControl` emits `{from,to}` ISO strings; `String({from,to})`
    // was `"[object Object]"`. Must serialize as nested bracket params matching
    // the SSR `router.get(path,{filters})` qs path.
    const fetcher = vi.fn().mockResolvedValue({ data: { value: 5, color: 'primary' } });
    const widget: WidgetPayload = {
      name: 'kpi',
      type: 'stat',
      heading: 'KPI',
      value: null,
      color: 'primary',
      data: null,
      deferred: true,
      dashboardId: 'overview',
      widgetId: 'kpi',
    };
    render(
      <WidgetRenderer
        widget={widget}
        filterValues={{ range: { from: '2026-01-01', to: '2026-02-01' } }}
        fetcher={fetcher}
      />,
    );
    await act(async () => {
      await Promise.resolve();
    });
    const calledUrl = fetcher.mock.calls[0]?.[0] as string;
    expect(calledUrl).toContain('filters%5Brange%5D%5Bfrom%5D=2026-01-01');
    expect(calledUrl).toContain('filters%5Brange%5D%5Bto%5D=2026-02-01');
    expect(calledUrl).not.toContain('object+Object');
  });

  it('skips empty/null leaves inside array and object filter values (issue #199)', async () => {
    const fetcher = vi.fn().mockResolvedValue({ data: { value: 5, color: 'primary' } });
    const widget: WidgetPayload = {
      name: 'kpi',
      type: 'stat',
      heading: 'KPI',
      value: null,
      color: 'primary',
      data: null,
      deferred: true,
      dashboardId: 'overview',
      widgetId: 'kpi',
    };
    render(
      <WidgetRenderer
        widget={widget}
        filterValues={{
          tags: ['a', null, '', 'b'],
          range: { from: '2026-01-01', to: null },
          status: '',
        }}
        fetcher={fetcher}
      />,
    );
    await act(async () => {
      await Promise.resolve();
    });
    const calledUrl = fetcher.mock.calls[0]?.[0] as string;
    expect(calledUrl).toContain('filters%5Btags%5D%5B%5D=a');
    expect(calledUrl).toContain('filters%5Btags%5D%5B%5D=b');
    // The null/'' array elements produce no extra params.
    expect((calledUrl.match(/filters%5Btags%5D%5B%5D=/g) ?? []).length).toBe(2);
    expect(calledUrl).toContain('filters%5Brange%5D%5Bfrom%5D=2026-01-01');
    // `to: null` leaf skipped.
    expect(calledUrl).not.toContain('filters%5Brange%5D%5Bto%5D');
    // Empty-string scalar skipped entirely.
    expect(calledUrl).not.toContain('filters%5Bstatus%5D');
  });
});

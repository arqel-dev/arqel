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
});

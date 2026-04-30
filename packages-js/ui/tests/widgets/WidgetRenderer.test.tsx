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

  it('polls the widget data endpoint at the configured interval', async () => {
    const fetcher = vi
      .fn()
      .mockResolvedValueOnce({ value: 10 })
      .mockResolvedValueOnce({ value: 20 });
    const widget: WidgetPayload = {
      name: 'kpi',
      type: 'stat',
      heading: 'KPI',
      value: 0,
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
  });

  it('fetches deferred widgets on mount when data is null', async () => {
    const fetcher = vi.fn().mockResolvedValue({ value: 99 });
    const widget: WidgetPayload = {
      name: 'kpi',
      type: 'stat',
      heading: 'KPI',
      value: null,
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
  });
});

import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ChartCard } from '../../src/widgets/ChartCard';
import type { ChartWidgetProps } from '../../src/widgets/types';

// Mock chart sub-modules so jsdom doesn't try to render Recharts SVG output.
// Each mock returns a tiny stub component that asserts its testid presence;
// this validates ChartCard's lazy-dispatch logic, not Recharts internals.
vi.mock('../../src/widgets/charts/LineChart', () => ({
  LineChart: () => <div data-testid="chart-line">line</div>,
}));
vi.mock('../../src/widgets/charts/BarChart', () => ({
  BarChart: () => <div data-testid="chart-bar">bar</div>,
}));
vi.mock('../../src/widgets/charts/AreaChart', () => ({
  AreaChart: () => <div data-testid="chart-area">area</div>,
}));
vi.mock('../../src/widgets/charts/PieChart', () => ({
  PieChart: () => <div data-testid="chart-pie">pie</div>,
}));
vi.mock('../../src/widgets/charts/DonutChart', () => ({
  DonutChart: () => <div data-testid="chart-donut">donut</div>,
}));
vi.mock('../../src/widgets/charts/RadarChart', () => ({
  RadarChart: () => <div data-testid="chart-radar">radar</div>,
}));

function makeWidget(
  overrides: Partial<ChartWidgetProps['widget']> = {},
): ChartWidgetProps['widget'] {
  return {
    name: 'sales',
    type: 'chart',
    heading: 'Sales by Month',
    description: 'Last 6 months',
    chartType: 'line',
    chartData: {
      labels: ['Jan', 'Feb', 'Mar'],
      datasets: [{ label: 'Revenue', data: [10, 20, 30] }],
    },
    chartOptions: {},
    height: 300,
    showLegend: true,
    showGrid: true,
    ...overrides,
  };
}

describe('ChartCard', () => {
  it('renders the suspense fallback before the lazy chunk resolves', () => {
    // First synchronous render — lazy components are not yet resolved, so
    // Suspense must show the skeleton fallback with role="status".
    const { container } = render(<ChartCard widget={makeWidget()} />);
    const fallback = container.querySelector('[data-testid="chart-fallback"]');
    expect(fallback).not.toBeNull();
  });

  it('dispatches LineChart for chartType="line"', async () => {
    render(<ChartCard widget={makeWidget({ chartType: 'line' })} />);
    expect(await screen.findByTestId('chart-line')).toBeInTheDocument();
  });

  it('dispatches BarChart for chartType="bar"', async () => {
    render(<ChartCard widget={makeWidget({ chartType: 'bar' })} />);
    expect(await screen.findByTestId('chart-bar')).toBeInTheDocument();
  });

  it('dispatches PieChart for chartType="pie"', async () => {
    render(<ChartCard widget={makeWidget({ chartType: 'pie' })} />);
    expect(await screen.findByTestId('chart-pie')).toBeInTheDocument();
  });

  it('dispatches DonutChart for chartType="donut"', async () => {
    render(<ChartCard widget={makeWidget({ chartType: 'donut' })} />);
    expect(await screen.findByTestId('chart-donut')).toBeInTheDocument();
  });

  it('dispatches RadarChart for chartType="radar"', async () => {
    render(<ChartCard widget={makeWidget({ chartType: 'radar' })} />);
    expect(await screen.findByTestId('chart-radar')).toBeInTheDocument();
  });

  it('renders heading and description from widget config', async () => {
    render(
      <ChartCard
        widget={makeWidget({
          heading: 'Quarterly Revenue',
          description: 'USD, net of refunds',
        })}
      />,
    );
    expect(await screen.findByText('Quarterly Revenue')).toBeInTheDocument();
    expect(screen.getByText('USD, net of refunds')).toBeInTheDocument();
  });
});

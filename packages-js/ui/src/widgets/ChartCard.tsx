import { type ComponentType, lazy, Suspense } from 'react';
import type { ChartSubProps, ChartType, ChartWidgetProps } from './types';
import { WidgetWrapper } from './WidgetWrapper';

/**
 * Lazy-loaded chart sub-components. Each `import()` becomes a separate chunk
 * in the build output so the ~60KB Recharts payload (and its sibling chart
 * primitives) stay out of the main `@arqel/ui` bundle. Dashboards that only
 * use StatCard never pay for Recharts.
 */
const LineChart = lazy(() => import('./charts/LineChart').then((m) => ({ default: m.LineChart })));
const BarChart = lazy(() => import('./charts/BarChart').then((m) => ({ default: m.BarChart })));
const AreaChart = lazy(() => import('./charts/AreaChart').then((m) => ({ default: m.AreaChart })));
const PieChart = lazy(() => import('./charts/PieChart').then((m) => ({ default: m.PieChart })));
const DonutChart = lazy(() =>
  import('./charts/DonutChart').then((m) => ({ default: m.DonutChart })),
);
const RadarChart = lazy(() =>
  import('./charts/RadarChart').then((m) => ({ default: m.RadarChart })),
);

const REGISTRY: Record<ChartType, ComponentType<ChartSubProps>> = {
  line: LineChart,
  bar: BarChart,
  area: AreaChart,
  pie: PieChart,
  donut: DonutChart,
  radar: RadarChart,
};

export function ChartCard({ widget }: ChartWidgetProps) {
  const Chart = REGISTRY[widget.chartType] ?? LineChart;

  return (
    <WidgetWrapper name={widget.name} heading={widget.heading} description={widget.description}>
      <Suspense
        fallback={
          <div
            role="status"
            aria-label="Loading chart"
            data-testid="chart-fallback"
            className="animate-pulse rounded bg-muted"
            style={{ height: widget.height }}
          />
        }
      >
        <Chart
          chartData={widget.chartData}
          chartOptions={widget.chartOptions}
          height={widget.height}
          showLegend={widget.showLegend}
          showGrid={widget.showGrid}
        />
      </Suspense>
    </WidgetWrapper>
  );
}

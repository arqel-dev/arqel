/**
 * `<ChartCard>` — placeholder shipped by this slice so `WidgetRenderer`
 * can dispatch on `type === 'chart'` while cluster B23 is in flight.
 * Replaced by the Recharts-backed implementation when B23 merges.
 */

import { WidgetWrapper } from './WidgetWrapper.js';

export interface ChartCardProps {
  widget: {
    name: string;
    heading?: string | null;
    description?: string | null;
    chartType?: string | null;
    data?: unknown;
    loadError?: string | null;
  };
}

export function ChartCard({ widget }: ChartCardProps) {
  return (
    <WidgetWrapper
      name={widget.name}
      heading={widget.heading ?? null}
      description={widget.description ?? null}
    >
      {widget.loadError ? (
        <div role="alert" className="text-sm text-red-600">
          {widget.loadError}
        </div>
      ) : (
        <div data-testid="chart-placeholder" data-chart-type={widget.chartType ?? 'line'}>
          chart
        </div>
      )}
    </WidgetWrapper>
  );
}

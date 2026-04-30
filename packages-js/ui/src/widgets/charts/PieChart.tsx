import {
  Cell,
  Legend,
  Pie,
  PieChart as RechartsPieChart,
  ResponsiveContainer,
  Tooltip,
} from 'recharts';
import { type ChartSubProps, colorFor } from '../types';

export interface PieChartProps extends ChartSubProps {
  innerRadius?: number | undefined;
  testId?: string | undefined;
}

export function PieChart({
  chartData,
  height,
  showLegend,
  innerRadius = 0,
  testId = 'chart-pie',
}: PieChartProps) {
  const first = chartData.datasets[0];
  const slices = (first?.data ?? []).map((value, i) => ({
    name: chartData.labels[i] ?? `Slice ${i + 1}`,
    value,
  }));

  return (
    <div data-testid={testId} style={{ width: '100%', height }}>
      <ResponsiveContainer width="100%" height="100%">
        <RechartsPieChart>
          <Pie
            data={slices}
            dataKey="value"
            nameKey="name"
            innerRadius={innerRadius}
            outerRadius="80%"
            label
          >
            {slices.map((slice, i) => (
              <Cell key={slice.name} fill={colorFor(first ?? { label: '', data: [] }, i)} />
            ))}
          </Pie>
          <Tooltip />
          {showLegend ? <Legend /> : null}
        </RechartsPieChart>
      </ResponsiveContainer>
    </div>
  );
}

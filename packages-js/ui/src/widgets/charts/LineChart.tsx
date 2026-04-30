import {
  CartesianGrid,
  Legend,
  Line,
  LineChart as RechartsLineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { type ChartSubProps, colorFor, toRowShape } from '../types';

export function LineChart({ chartData, height, showLegend, showGrid }: ChartSubProps) {
  const rows = toRowShape(chartData);
  return (
    <div data-testid="chart-line" style={{ width: '100%', height }}>
      <ResponsiveContainer width="100%" height="100%">
        <RechartsLineChart data={rows}>
          {showGrid ? <CartesianGrid strokeDasharray="3 3" /> : null}
          <XAxis dataKey="name" />
          <YAxis />
          <Tooltip />
          {showLegend ? <Legend /> : null}
          {chartData.datasets.map((ds, i) => (
            <Line
              key={ds.label}
              type="monotone"
              dataKey={`dataset${i}`}
              name={ds.label}
              stroke={colorFor(ds, i)}
              strokeWidth={2}
              dot={false}
            />
          ))}
        </RechartsLineChart>
      </ResponsiveContainer>
    </div>
  );
}

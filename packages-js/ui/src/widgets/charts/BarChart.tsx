import {
  Bar,
  CartesianGrid,
  Legend,
  BarChart as RechartsBarChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { type ChartSubProps, colorFor, toRowShape } from '../types';

export function BarChart({ chartData, height, showLegend, showGrid }: ChartSubProps) {
  const rows = toRowShape(chartData);
  return (
    <div data-testid="chart-bar" style={{ width: '100%', height }}>
      <ResponsiveContainer width="100%" height="100%">
        <RechartsBarChart data={rows}>
          {showGrid ? <CartesianGrid strokeDasharray="3 3" /> : null}
          <XAxis dataKey="name" />
          <YAxis />
          <Tooltip />
          {showLegend ? <Legend /> : null}
          {chartData.datasets.map((ds, i) => (
            <Bar key={ds.label} dataKey={`dataset${i}`} name={ds.label} fill={colorFor(ds, i)} />
          ))}
        </RechartsBarChart>
      </ResponsiveContainer>
    </div>
  );
}

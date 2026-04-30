import {
  Area,
  CartesianGrid,
  Legend,
  AreaChart as RechartsAreaChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { type ChartSubProps, colorFor, toRowShape } from '../types';

export function AreaChart({ chartData, height, showLegend, showGrid }: ChartSubProps) {
  const rows = toRowShape(chartData);
  return (
    <div data-testid="chart-area" style={{ width: '100%', height }}>
      <ResponsiveContainer width="100%" height="100%">
        <RechartsAreaChart data={rows}>
          {showGrid ? <CartesianGrid strokeDasharray="3 3" /> : null}
          <XAxis dataKey="name" />
          <YAxis />
          <Tooltip />
          {showLegend ? <Legend /> : null}
          {chartData.datasets.map((ds, i) => {
            const color = colorFor(ds, i);
            return (
              <Area
                key={ds.label}
                type="monotone"
                dataKey={`dataset${i}`}
                name={ds.label}
                stroke={color}
                fill={color}
                fillOpacity={0.3}
              />
            );
          })}
        </RechartsAreaChart>
      </ResponsiveContainer>
    </div>
  );
}

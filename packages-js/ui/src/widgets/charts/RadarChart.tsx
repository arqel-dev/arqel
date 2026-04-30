import {
  Legend,
  PolarAngleAxis,
  PolarGrid,
  PolarRadiusAxis,
  Radar,
  RadarChart as RechartsRadarChart,
  ResponsiveContainer,
  Tooltip,
} from 'recharts';
import { type ChartSubProps, colorFor, toRowShape } from '../types';

export function RadarChart({ chartData, height, showLegend, showGrid }: ChartSubProps) {
  const rows = toRowShape(chartData);

  return (
    <div data-testid="chart-radar" style={{ width: '100%', height }}>
      <ResponsiveContainer width="100%" height="100%">
        <RechartsRadarChart data={rows}>
          {showGrid ? <PolarGrid /> : null}
          <PolarAngleAxis dataKey="name" />
          <PolarRadiusAxis />
          {chartData.datasets.map((ds, i) => {
            const color = colorFor(ds, i);
            return (
              <Radar
                key={ds.label}
                dataKey={`dataset${i}`}
                name={ds.label}
                stroke={color}
                fill={color}
                fillOpacity={0.3}
              />
            );
          })}
          <Tooltip />
          {showLegend ? <Legend /> : null}
        </RechartsRadarChart>
      </ResponsiveContainer>
    </div>
  );
}

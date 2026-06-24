import { useArqelLocale } from '@arqel-dev/react/utils';
import { useMemo } from 'react';
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
  const locale = useArqelLocale();
  const nf = useMemo(() => new Intl.NumberFormat(locale), [locale]);

  return (
    <div data-testid="chart-radar" style={{ width: '100%', height }}>
      <ResponsiveContainer width="100%" height="100%">
        <RechartsRadarChart data={rows}>
          {showGrid ? <PolarGrid /> : null}
          <PolarAngleAxis dataKey="name" />
          <PolarRadiusAxis tickFormatter={(v) => nf.format(Number(v))} />
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
          <Tooltip formatter={(v) => nf.format(Number(v))} />
          {showLegend ? <Legend /> : null}
        </RechartsRadarChart>
      </ResponsiveContainer>
    </div>
  );
}

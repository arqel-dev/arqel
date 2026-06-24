import { useArqelLocale, useArqelTranslations } from '@arqel-dev/react/utils';
import { useMemo } from 'react';
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
  const locale = useArqelLocale();
  const t = useArqelTranslations();
  const nf = useMemo(() => new Intl.NumberFormat(locale), [locale]);
  const first = chartData.datasets[0];
  const slices = (first?.data ?? []).map((value, i) => ({
    name:
      chartData.labels[i] ?? t('arqel.chart.slice_fallback', 'Slice :number', { number: i + 1 }),
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
            label={(entry) => nf.format(Number(entry.value))}
          >
            {slices.map((slice, i) => (
              <Cell key={slice.name} fill={colorFor(first ?? { label: '', data: [] }, i)} />
            ))}
          </Pie>
          <Tooltip formatter={(v) => nf.format(Number(v))} />
          {showLegend ? <Legend /> : null}
        </RechartsPieChart>
      </ResponsiveContainer>
    </div>
  );
}

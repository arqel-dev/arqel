import type { ChartSubProps } from '../types';
import { PieChart } from './PieChart';

/**
 * DonutChart is a PieChart with a non-zero innerRadius. We delegate so the
 * Recharts primitives stay in one chunk per shape (pie ≡ donut visually).
 */
export function DonutChart(props: ChartSubProps) {
  return <PieChart {...props} innerRadius={60} testId="chart-donut" />;
}

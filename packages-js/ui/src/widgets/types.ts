export type ChartType = 'line' | 'bar' | 'area' | 'pie' | 'donut' | 'radar';

export interface ChartDataset {
  label: string;
  data: number[];
  color?: string;
}

export interface ChartData {
  labels: string[];
  datasets: ChartDataset[];
}

export interface ChartWidgetProps {
  widget: {
    name: string;
    type: 'chart';
    heading?: string | undefined;
    description?: string | undefined;
    chartType: ChartType;
    chartData: ChartData;
    chartOptions?: Record<string, unknown> | undefined;
    height: number;
    showLegend: boolean;
    showGrid: boolean;
  };
}

export interface ChartSubProps {
  chartData: ChartData;
  chartOptions?: Record<string, unknown> | undefined;
  height: number;
  showLegend: boolean;
  showGrid: boolean;
}

/**
 * Translates the PHP-side `{labels, datasets}` shape into Recharts' expected
 * row shape: `[{ name: label, dataset1: value, dataset2: value, ... }]`.
 *
 * Dataset keys are stable indices (`dataset0`, `dataset1`, ...) so chart
 * components can pair them with their human label/color via the datasets
 * array passed to `<Line dataKey="dataset0" name={datasets[0].label} />`.
 */
export function toRowShape(data: ChartData): Array<Record<string, number | string>> {
  return data.labels.map((label, i) => {
    const row: Record<string, number | string> = { name: label };
    data.datasets.forEach((ds, j) => {
      row[`dataset${j}`] = ds.data[i] ?? 0;
    });
    return row;
  });
}

const PALETTE = [
  '#2563eb',
  '#10b981',
  '#f59e0b',
  '#ef4444',
  '#8b5cf6',
  '#ec4899',
  '#14b8a6',
  '#f97316',
];

export function colorFor(dataset: ChartDataset, index: number): string {
  return dataset.color ?? PALETTE[index % PALETTE.length] ?? '#2563eb';
}

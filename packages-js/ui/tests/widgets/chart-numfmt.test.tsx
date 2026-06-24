import { render } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// Capture the props recharts primitives receive so we can exercise the
// locale-aware `tickFormatter` / `formatter` / pie `label` functions the chart
// components wire up — without depending on Recharts' SVG layout (which needs a
// sized container that jsdom does not provide).
const captured: {
  yAxis: Array<Record<string, unknown>>;
  tooltip: Array<Record<string, unknown>>;
  pie: Array<Record<string, unknown>>;
  polarRadius: Array<Record<string, unknown>>;
} = { yAxis: [], tooltip: [], pie: [], polarRadius: [] };

vi.mock('recharts', () => {
  const passthrough =
    (bucket?: keyof typeof captured) =>
    (props: Record<string, unknown> & { children?: ReactNode }) => {
      if (bucket) {
        captured[bucket].push(props);
      }
      return <div>{props.children ?? null}</div>;
    };
  return {
    ResponsiveContainer: passthrough(),
    CartesianGrid: passthrough(),
    Legend: passthrough(),
    Tooltip: passthrough('tooltip'),
    XAxis: passthrough(),
    YAxis: passthrough('yAxis'),
    Line: passthrough(),
    LineChart: passthrough(),
    Bar: passthrough(),
    BarChart: passthrough(),
    Area: passthrough(),
    AreaChart: passthrough(),
    Cell: passthrough(),
    Pie: passthrough('pie'),
    PieChart: passthrough(),
    PolarAngleAxis: passthrough(),
    PolarGrid: passthrough(),
    PolarRadiusAxis: passthrough('polarRadius'),
    Radar: passthrough(),
    RadarChart: passthrough(),
  };
});

const { pageMock } = vi.hoisted(() => ({
  pageMock: vi.fn(() => ({ props: {} as Record<string, unknown> })),
}));
vi.mock('@inertiajs/react', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/react')>();
  return { ...actual, usePage: pageMock };
});

import { AreaChart } from '../../src/widgets/charts/AreaChart';
import { BarChart } from '../../src/widgets/charts/BarChart';
import { LineChart } from '../../src/widgets/charts/LineChart';
import { PieChart } from '../../src/widgets/charts/PieChart';
import { RadarChart } from '../../src/widgets/charts/RadarChart';
import type { ChartData } from '../../src/widgets/types';

function ptBR(): void {
  pageMock.mockReturnValue({ props: { i18n: { locale: 'pt_BR' } } });
}

const bigData: ChartData = {
  labels: ['Jan', 'Feb'],
  datasets: [{ label: 'Revenue', data: [1500000, 2300000] }],
};

beforeEach(() => {
  captured.yAxis = [];
  captured.tooltip = [];
  captured.pie = [];
  captured.polarRadius = [];
});

afterEach(() => {
  pageMock.mockReturnValue({ props: {} });
});

describe('chart numeric locale formatting', () => {
  it('LineChart formats YAxis ticks and tooltip values with pt-BR grouping', () => {
    ptBR();
    render(<LineChart chartData={bigData} height={300} showLegend={false} showGrid={false} />);

    const tick = captured.yAxis[0]?.['tickFormatter'] as (v: unknown) => string;
    const tip = captured.tooltip[0]?.['formatter'] as (v: unknown) => string;
    expect(tick).toBeTypeOf('function');
    expect(tip).toBeTypeOf('function');
    expect(tick(1500000)).toBe('1.500.000');
    expect(tip(2300000)).toBe('2.300.000');
  });

  it('BarChart formats YAxis ticks with the active locale', () => {
    ptBR();
    render(<BarChart chartData={bigData} height={300} showLegend={false} showGrid={false} />);
    const tick = captured.yAxis[0]?.['tickFormatter'] as (v: unknown) => string;
    expect(tick(1500000)).toBe('1.500.000');
  });

  it('AreaChart formats tooltip values with the active locale', () => {
    ptBR();
    render(<AreaChart chartData={bigData} height={300} showLegend={false} showGrid={false} />);
    const tip = captured.tooltip[0]?.['formatter'] as (v: unknown) => string;
    expect(tip(1500000)).toBe('1.500.000');
  });

  it('RadarChart formats PolarRadiusAxis ticks with the active locale', () => {
    ptBR();
    render(<RadarChart chartData={bigData} height={300} showLegend={false} showGrid={false} />);
    const tick = captured.polarRadius[0]?.['tickFormatter'] as (v: unknown) => string;
    expect(tick(1500000)).toBe('1.500.000');
  });

  it('LineChart uses en grouping when no locale prop is present (en fallback)', () => {
    render(<LineChart chartData={bigData} height={300} showLegend={false} showGrid={false} />);
    const tick = captured.yAxis[0]?.['tickFormatter'] as (v: unknown) => string;
    expect(tick(1500000)).toBe('1,500,000');
  });

  it('PieChart formats slice value labels/tooltips with the active locale', () => {
    ptBR();
    const data: ChartData = {
      labels: ['A', 'B'],
      datasets: [{ label: 'Sales', data: [1200000, 800000] }],
    };
    render(<PieChart chartData={data} height={300} showLegend={false} showGrid={false} />);
    const label = captured.pie[0]?.['label'] as (entry: { value: number }) => string;
    const tip = captured.tooltip[0]?.['formatter'] as (v: unknown) => string;
    expect(label({ value: 1200000 })).toBe('1.200.000');
    expect(tip(800000)).toBe('800.000');
  });

  it('PieChart localizes the "Slice :number" fallback when a label is missing', () => {
    pageMock.mockReturnValue({
      props: {
        i18n: {
          locale: 'pt_BR',
          translations: { arqel: { chart: { slice_fallback: 'Fatia :number' } } },
        },
      },
    });
    const data: ChartData = {
      labels: ['A'],
      datasets: [{ label: 'Sales', data: [10, 20] }],
    };
    render(<PieChart chartData={data} height={300} showLegend={false} showGrid={false} />);
    const slices = captured.pie[0]?.['data'] as Array<{ name: string }>;
    expect(slices[0]?.name).toBe('A');
    expect(slices[1]?.name).toBe('Fatia 2');
  });

  it('PieChart slice fallback uses the en literal when no dictionary is present', () => {
    const data: ChartData = {
      labels: [],
      datasets: [{ label: 'Sales', data: [10] }],
    };
    render(<PieChart chartData={data} height={300} showLegend={false} showGrid={false} />);
    const slices = captured.pie[0]?.['data'] as Array<{ name: string }>;
    expect(slices[0]?.name).toBe('Slice 1');
  });
});

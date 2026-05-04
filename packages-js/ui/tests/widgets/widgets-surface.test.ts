import { describe, expect, it } from 'vitest';
import type {
  ChartData,
  ChartDataset,
  ChartSubProps,
  ChartType,
  ChartWidgetProps,
  DashboardFilterPayload,
  DashboardFiltersProps,
  DashboardGridProps,
  DashboardPayload,
  DateRangeFilterPayload,
  SelectFilterPayload,
  StatCardColor,
  StatCardProps,
  StatCardWidget,
  TableCardColumn,
  TableCardProps,
  TableCardRecord,
  TableCardWidget,
  WidgetPayload,
  WidgetRendererProps,
  WidgetWrapperProps,
} from '../../src/widgets/index.js';
import {
  ChartCard,
  DashboardFilters,
  DashboardGrid,
  StatCard,
  TableCard,
  WidgetRenderer,
  WidgetWrapper,
} from '../../src/widgets/index.js';

describe('@arqel-dev/ui/widgets — module surface', () => {
  it('exports all 7 widget components as functions', () => {
    expect(typeof StatCard).toBe('function');
    expect(typeof ChartCard).toBe('function');
    expect(typeof TableCard).toBe('function');
    expect(typeof DashboardGrid).toBe('function');
    expect(typeof DashboardFilters).toBe('function');
    expect(typeof WidgetRenderer).toBe('function');
    expect(typeof WidgetWrapper).toBe('function');
  });

  it('compiles all type exports referenced by consumers', () => {
    // Pure type-level check: if any of these names disappear from the
    // barrel, `tsc --noEmit` (run via `pnpm typecheck`) fails this file.
    type _Surface = [
      ChartData,
      ChartDataset,
      ChartSubProps,
      ChartType,
      ChartWidgetProps,
      DashboardFilterPayload,
      DashboardFiltersProps,
      DashboardGridProps,
      DashboardPayload,
      DateRangeFilterPayload,
      SelectFilterPayload,
      StatCardColor,
      StatCardProps,
      StatCardWidget,
      TableCardColumn,
      TableCardProps,
      TableCardRecord,
      TableCardWidget,
      WidgetPayload,
      WidgetRendererProps,
      WidgetWrapperProps,
    ];
    const names: ReadonlyArray<keyof { [K in keyof _Surface as `t${K & number}`]: _Surface[K] }> =
      [];
    expect(names).toEqual([]);
  });
});

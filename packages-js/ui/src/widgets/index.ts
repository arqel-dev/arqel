/**
 * `@arqel/ui/widgets` — React components for dashboard widget rendering.
 *
 * Phase 2 ships StatCard (KPI), ChartCard (Recharts polymorphic),
 * TableCard (mini DataTable), DashboardGrid + WidgetRenderer +
 * DashboardFilters + WidgetWrapper (chrome).
 */

export { ChartCard } from './ChartCard.js';
export type {
  DashboardFilterPayload,
  DashboardFiltersProps,
  DateRangeFilterPayload,
  SelectFilterPayload,
} from './DashboardFilters.js';
export { DashboardFilters } from './DashboardFilters.js';
export type { DashboardGridProps, DashboardPayload } from './DashboardGrid.js';
export { DashboardGrid } from './DashboardGrid.js';
export type { StatCardColor, StatCardProps, StatCardWidget } from './StatCard.js';
export { StatCard } from './StatCard.js';
export type {
  TableCardColumn,
  TableCardProps,
  TableCardRecord,
  TableCardWidget,
} from './TableCard.js';
export { TableCard } from './TableCard.js';
export type {
  ChartData,
  ChartDataset,
  ChartSubProps,
  ChartType,
  ChartWidgetProps,
} from './types.js';
export type { WidgetPayload, WidgetRendererProps } from './WidgetRenderer.js';
export { WidgetRenderer } from './WidgetRenderer.js';
export type { WidgetWrapperProps } from './WidgetWrapper.js';
export { WidgetWrapper } from './WidgetWrapper.js';

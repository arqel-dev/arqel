/**
 * `@arqel/ui/widgets` — React components for dashboard widget rendering.
 *
 * Phase 2 ships StatCard (KPI), ChartCard (Recharts polymorphic),
 * TableCard (mini DataTable), DashboardGrid + WidgetRenderer +
 * DashboardFilters + WidgetWrapper (chrome).
 */

export { ChartCard } from './ChartCard.js';
export type { StatCardColor, StatCardProps, StatCardWidget } from './StatCard.js';
export { StatCard } from './StatCard.js';
export type {
  ChartData,
  ChartDataset,
  ChartSubProps,
  ChartType,
  ChartWidgetProps,
} from './types.js';
export type { WidgetWrapperProps } from './WidgetWrapper.js';
export { WidgetWrapper } from './WidgetWrapper.js';

/**
 * `@arqel/ui/widgets` — dashboard widget primitives.
 *
 * Subpath barrel for the widget cards (StatCard, ChartCard, TableCard),
 * the shared chrome (`WidgetWrapper`), the renderer dispatcher
 * (`WidgetRenderer`), the filter bar (`DashboardFilters`) and the grid
 * layout (`DashboardGrid`).
 */

export type { ChartCardProps } from './ChartCard.js';
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
export type { StatCardProps } from './StatCard.js';
export { StatCard } from './StatCard.js';
export type {
  TableCardColumn,
  TableCardProps,
  TableCardRecord,
  TableCardWidget,
} from './TableCard.js';
export { TableCard } from './TableCard.js';
export type { WidgetPayload, WidgetRendererProps } from './WidgetRenderer.js';
export { WidgetRenderer } from './WidgetRenderer.js';
export type { WidgetWrapperProps } from './WidgetWrapper.js';
export { WidgetWrapper } from './WidgetWrapper.js';

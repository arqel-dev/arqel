// Public surface for @arqel/ui/widgets. Cluster A23 owns the bulk of this
// barrel (StatCard, TableCard, registry, error boundary). The ChartCard slice
// adds its export here so the orchestrator can reconcile cleanly.
export { ChartCard } from './ChartCard';
export type {
  ChartData,
  ChartDataset,
  ChartSubProps,
  ChartType,
  ChartWidgetProps,
} from './types';
export { WidgetWrapper } from './WidgetWrapper';

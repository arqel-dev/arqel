/**
 * `<StatCard>` — placeholder shipped by this slice so `WidgetRenderer`
 * can dispatch on `type === 'stat'` while cluster A23 is in flight.
 * Replaced by the canonical implementation when A23 merges.
 */

import { WidgetWrapper } from './WidgetWrapper.js';

export interface StatCardProps {
  widget: {
    name: string;
    heading?: string | null;
    description?: string | null;
    value?: string | number | null;
    loadError?: string | null;
  };
}

export function StatCard({ widget }: StatCardProps) {
  return (
    <WidgetWrapper
      name={widget.name}
      heading={widget.heading ?? null}
      description={widget.description ?? null}
    >
      {widget.loadError ? (
        <div role="alert" className="text-sm text-red-600">
          {widget.loadError}
        </div>
      ) : (
        <div className="text-2xl font-semibold" data-testid="stat-value">
          {widget.value ?? '—'}
        </div>
      )}
    </WidgetWrapper>
  );
}

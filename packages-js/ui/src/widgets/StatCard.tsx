/**
 * `<StatCard>` — KPI / "big number" widget.
 *
 * Renders the payload produced by `Arqel\Widgets\StatWidget::data()`:
 *
 *   {
 *     name, type: 'stat', heading?, description?,
 *     value, statDescription?, descriptionIcon?,
 *     color, icon?, chart?: number[], url?
 *   }
 *
 * Includes a tiny inline-SVG sparkline (no Recharts dep) when `chart`
 * is set. ChartCard / TableCard (Recharts-based) ship in a separate
 * cluster; this component is intentionally Recharts-free so the
 * common "big number" case stays bundle-cheap.
 */

import type { ReactNode } from 'react';
import { cn } from '../utils/cn.js';
import { WidgetWrapper } from './WidgetWrapper.js';

export type StatCardColor = 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | 'info';

export interface StatCardWidget {
  name: string;
  type: 'stat';
  heading?: string;
  description?: string;
  value: string | number | null;
  statDescription?: string | null;
  descriptionIcon?: string | null;
  color: StatCardColor;
  icon?: string | null;
  chart?: number[] | null;
  url?: string | null;
}

export interface StatCardProps {
  widget: StatCardWidget;
  className?: string | undefined;
  columnSpan?: number | string | undefined;
}

const COLOR_TEXT: Record<StatCardColor, string> = {
  primary: 'text-primary',
  secondary: 'text-secondary-foreground',
  success: 'text-green-600',
  warning: 'text-amber-600',
  danger: 'text-red-600',
  info: 'text-sky-600',
};

const COLOR_STROKE: Record<StatCardColor, string> = {
  primary: 'stroke-primary',
  secondary: 'stroke-secondary-foreground',
  success: 'stroke-green-500',
  warning: 'stroke-amber-500',
  danger: 'stroke-red-500',
  info: 'stroke-sky-500',
};

interface SparklineProps {
  points: number[];
  color: StatCardColor;
}

function Sparkline({ points, color }: SparklineProps) {
  if (points.length < 2) return null;

  const min = Math.min(...points);
  const max = Math.max(...points);
  const range = max - min || 1;
  const stepX = 100 / (points.length - 1);

  const coords = points
    .map((value, index) => {
      const x = index * stepX;
      // Invert Y so larger values render higher in the viewBox.
      const y = 30 - ((value - min) / range) * 30;
      return `${x.toFixed(2)},${y.toFixed(2)}`;
    })
    .join(' ');

  return (
    <svg
      viewBox="0 0 100 30"
      preserveAspectRatio="none"
      className={cn('mt-3 h-8 w-full', COLOR_STROKE[color])}
      role="img"
      aria-label="Trend sparkline"
      data-testid="stat-card-sparkline"
    >
      <polyline
        fill="none"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinecap="round"
        strokeLinejoin="round"
        points={coords}
      />
    </svg>
  );
}

export function StatCard({ widget, className, columnSpan }: StatCardProps) {
  const { heading, description, value, statDescription, descriptionIcon, color, chart, url } =
    widget;

  const hasChart = Array.isArray(chart) && chart.length >= 2;

  const body: ReactNode = (
    <>
      <div className={cn('text-4xl font-bold tabular-nums', COLOR_TEXT[color])}>{value ?? '—'}</div>
      {statDescription && (
        <div className="mt-2 flex items-center gap-1 text-xs text-muted-foreground">
          {descriptionIcon && (
            <span aria-hidden="true" data-testid="stat-card-icon">
              {/* Icon name passed through; an icon registry resolves it app-side. */}
              {descriptionIcon}
            </span>
          )}
          <span>{statDescription}</span>
        </div>
      )}
      {hasChart && <Sparkline points={chart as number[]} color={color} />}
    </>
  );

  const inner = url ? (
    <a
      href={url}
      className="block rounded outline-none focus-visible:ring-2 focus-visible:ring-ring"
      data-testid="stat-card-link"
    >
      {body}
    </a>
  ) : (
    body
  );

  return (
    <WidgetWrapper
      heading={heading}
      description={description}
      columnSpan={columnSpan}
      className={className}
    >
      {inner}
    </WidgetWrapper>
  );
}

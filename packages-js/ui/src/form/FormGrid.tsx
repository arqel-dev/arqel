/**
 * `<FormGrid>` — responsive CSS-grid container.
 */

import type { GridProps } from '@arqel-dev/types/forms';
import type { ReactNode } from 'react';
import { cn } from '../utils/cn.js';

export interface FormGridProps {
  config: GridProps;
  children: ReactNode;
  className?: string;
}

const BREAKPOINT_PREFIX: Record<string, string> = {
  sm: 'sm:',
  md: 'md:',
  lg: 'lg:',
  xl: 'xl:',
  '2xl': '2xl:',
};

export function FormGrid({ config, children, className }: FormGridProps) {
  return (
    <div
      className={cn('grid', gridColsClass(config.columns), className)}
      style={{ gap: config.gap ?? '1rem' }}
    >
      {children}
    </div>
  );
}

/**
 * Emit responsive Tailwind column classes from a flat int or a
 * `{sm,md,lg,xl,2xl}` map. Mirrors `<DashboardGrid>`'s generator so a
 * `{sm:1, md:2, lg:3}` map genuinely reflows (1 col on mobile → multi-col on
 * desktop) instead of locking to a single inline `grid-template-columns` value.
 */
function gridColsClass(columns: GridProps['columns']): string {
  if (typeof columns === 'number') {
    return `grid-cols-${Math.max(1, Math.min(12, columns))}`;
  }
  // Base fallback = 1 column on the smallest screens.
  const parts: string[] = ['grid-cols-1'];
  for (const bp of ['sm', 'md', 'lg', 'xl', '2xl'] as const) {
    const value = columns[bp];
    if (typeof value === 'number') {
      parts.push(`${BREAKPOINT_PREFIX[bp]}grid-cols-${Math.max(1, Math.min(12, value))}`);
    }
  }
  return parts.join(' ');
}

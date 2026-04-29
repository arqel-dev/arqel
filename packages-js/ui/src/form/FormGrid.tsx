/**
 * `<FormGrid>` — responsive CSS-grid container.
 */

import type { GridProps } from '@arqel/types/forms';
import type { ReactNode } from 'react';
import { cn } from '../utils/cn.js';

export interface FormGridProps {
  config: GridProps;
  children: ReactNode;
  className?: string;
}

export function FormGrid({ config, children, className }: FormGridProps) {
  const columns = typeof config.columns === 'number' ? config.columns : (config.columns['md'] ?? 1);
  return (
    <div
      className={cn('grid', className)}
      style={{
        gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))`,
        gap: config.gap ?? '1rem',
      }}
    >
      {children}
    </div>
  );
}

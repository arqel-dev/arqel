/**
 * `<EmptyState>` — standardised empty-state block.
 *
 * Used by DataTable's empty slot and as a stand-alone fallback when
 * a resource collection has zero records.
 */

import type { ReactNode } from 'react';
import { cn } from '../utils/cn.js';

export interface EmptyStateProps {
  title: ReactNode;
  description?: ReactNode;
  icon?: ReactNode;
  action?: ReactNode;
  className?: string;
}

export function EmptyState({ title, description, icon, action, className }: EmptyStateProps) {
  return (
    <div
      className={cn(
        'flex flex-col items-center gap-3 px-4 py-12 text-center text-muted-foreground',
        className,
      )}
    >
      {icon && <div className="text-3xl">{icon}</div>}
      <h2 className="text-base font-semibold text-foreground">{title}</h2>
      {description && <p className="max-w-md text-sm">{description}</p>}
      {action && <div className="mt-2">{action}</div>}
    </div>
  );
}

/**
 * `<PageHeader>` — title + optional description + action slot.
 *
 * Used at the top of Resource pages right under `<Breadcrumbs>`. Pass
 * `actions` to render a row of buttons aligned right.
 */

import type { ReactNode } from 'react';
import { cn } from '../utils/cn.js';

export interface PageHeaderProps {
  title: ReactNode;
  description?: ReactNode;
  actions?: ReactNode;
  className?: string;
}

export function PageHeader({ title, description, actions, className }: PageHeaderProps) {
  return (
    <header className={cn('flex flex-wrap items-start gap-4', className)}>
      <div className="min-w-0 flex-1">
        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
        {description && (
          <p className="mt-1 text-sm text-[var(--color-arqel-muted-fg)]">{description}</p>
        )}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </header>
  );
}

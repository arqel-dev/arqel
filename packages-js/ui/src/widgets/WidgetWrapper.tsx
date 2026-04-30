/**
 * `<WidgetWrapper>` — minimal common chrome around every widget card.
 *
 * Cluster A23 ships the canonical implementation (heading, description,
 * loading skeleton, error boundary). This module is the WIDGETS-010 part-3
 * placeholder so `TableCard`, `WidgetRenderer` and `DashboardGrid` can
 * compile and be tested in isolation. The orchestrator overwrites this
 * file during the merge of cluster A23.
 */

import type { ReactNode } from 'react';
import { cn } from '../utils/cn.js';

export interface WidgetWrapperProps {
  name: string;
  heading?: string | null | undefined;
  description?: string | null | undefined;
  className?: string | undefined;
  children?: ReactNode;
}

export function WidgetWrapper({
  name,
  heading,
  description,
  className,
  children,
}: WidgetWrapperProps) {
  return (
    <section
      aria-label={heading ?? name}
      data-widget={name}
      className={cn(
        'rounded-[var(--radius-arqel-md)] border border-[var(--color-arqel-border)]',
        'bg-[var(--color-arqel-bg)] p-4 shadow-sm',
        className,
      )}
    >
      {(heading || description) && (
        <header className="mb-3 space-y-1">
          {heading && <h3 className="text-sm font-semibold">{heading}</h3>}
          {description && (
            <p className="text-xs text-[var(--color-arqel-muted-fg)]">{description}</p>
          )}
        </header>
      )}
      {children}
    </section>
  );
}

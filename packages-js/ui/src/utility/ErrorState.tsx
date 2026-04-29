/**
 * `<ErrorState>` — generic error block (404 / 403 / 500).
 *
 * Phase 1 ships a plain layout; richer illustrations / Heroicons land
 * with `@arqel/fields` icon registry.
 */

import type { ReactNode } from 'react';
import { cn } from '../utils/cn.js';

export interface ErrorStateProps {
  status?: number;
  title: ReactNode;
  description?: ReactNode;
  action?: ReactNode;
  className?: string;
}

export function ErrorState({ status, title, description, action, className }: ErrorStateProps) {
  return (
    <div
      role="alert"
      className={cn('flex flex-col items-center gap-3 px-4 py-16 text-center', className)}
    >
      {status && (
        <span className="text-sm font-mono text-[var(--color-arqel-muted-fg)]">{status}</span>
      )}
      <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
      {description && (
        <p className="max-w-md text-sm text-[var(--color-arqel-muted-fg)]">{description}</p>
      )}
      {action && <div className="mt-2">{action}</div>}
    </div>
  );
}

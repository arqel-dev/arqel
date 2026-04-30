import type { ReactNode } from 'react';

/**
 * Minimal stub for WidgetWrapper — Cluster A23 ships the full implementation
 * in parallel. This local copy exists only so this worktree can build/test
 * the ChartCard slice in isolation. The orchestrator reconciles by keeping
 * A23's richer version (chrome, error boundary, loading states).
 */
export interface WidgetWrapperProps {
  name: string;
  heading?: string | undefined;
  description?: string | undefined;
  children: ReactNode;
}

export function WidgetWrapper({
  name,
  heading,
  description,
  children,
}: WidgetWrapperProps): ReactNode {
  return (
    <section
      aria-label={heading ?? name}
      data-widget={name}
      className="rounded-lg border border-border bg-card p-4 shadow-sm"
    >
      {heading ? (
        <header className="mb-2 space-y-1">
          <h3 className="text-sm font-medium text-foreground">{heading}</h3>
          {description ? <p className="text-xs text-muted-foreground">{description}</p> : null}
        </header>
      ) : null}
      <div className="widget-body">{children}</div>
    </section>
  );
}

/**
 * `<AppShell>` — top-level admin layout wrapper.
 *
 * Four variants control where structural slots land:
 *  - `sidebar-left` (default): sidebar on the left, topbar above main
 *  - `sidebar-right`: sidebar on the right
 *  - `topbar-only`: full-width content under the topbar (no sidebar)
 *  - `full-width`: bare content, no chrome
 *
 * Slots (`sidebar`, `topbar`, `footer`, `children`) are passed as React
 * nodes so callers can compose `<Sidebar />` / `<Topbar />` themselves
 * or substitute custom shells per panel.
 */

import type { ReactNode } from 'react';
import { cn } from '../utils/cn.js';

export type AppShellVariant = 'sidebar-left' | 'sidebar-right' | 'topbar-only' | 'full-width';

export interface AppShellProps {
  variant?: AppShellVariant;
  sidebar?: ReactNode;
  topbar?: ReactNode;
  footer?: ReactNode;
  children: ReactNode;
  className?: string;
}

export function AppShell({
  variant = 'sidebar-left',
  sidebar,
  topbar,
  footer,
  children,
  className,
}: AppShellProps) {
  if (variant === 'full-width') {
    return (
      <div
        data-arqel-shell="full-width"
        className={cn(
          'min-h-screen bg-[var(--color-arqel-bg)] text-[var(--color-arqel-fg)]',
          className,
        )}
      >
        {children}
      </div>
    );
  }

  if (variant === 'topbar-only') {
    return (
      <div
        data-arqel-shell="topbar-only"
        className={cn(
          'flex min-h-screen flex-col bg-[var(--color-arqel-bg)] text-[var(--color-arqel-fg)]',
          className,
        )}
      >
        {topbar}
        <main className="flex-1">{children}</main>
        {footer}
      </div>
    );
  }

  const sidebarSide = variant === 'sidebar-right' ? 'right' : 'left';

  return (
    <div
      data-arqel-shell={variant}
      data-sidebar-side={sidebarSide}
      className={cn(
        'flex min-h-screen bg-[var(--color-arqel-bg)] text-[var(--color-arqel-fg)]',
        sidebarSide === 'right' && 'flex-row-reverse',
        className,
      )}
    >
      {sidebar}
      <div className="flex min-w-0 flex-1 flex-col">
        {topbar}
        <main className="flex-1">{children}</main>
        {footer}
      </div>
    </div>
  );
}

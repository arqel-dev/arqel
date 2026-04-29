/**
 * `<MainContent>` — padded content wrapper used inside `<AppShell>`.
 *
 * Accepts breadcrumb and header slots so pages don't need to redefine
 * their own scaffolding. `maxWidth` mirrors the Tailwind container scale
 * — `none` opts out of any constraint (useful for full-bleed dashboards).
 */

import type { ReactNode } from 'react';
import { cn } from '../utils/cn.js';

export type MainContentMaxWidth = 'none' | 'md' | 'lg' | 'xl' | '2xl' | '4xl' | '6xl' | '7xl';

export interface MainContentProps {
  breadcrumbs?: ReactNode;
  header?: ReactNode;
  children: ReactNode;
  maxWidth?: MainContentMaxWidth;
  className?: string;
}

const MAX_WIDTH_CLASSES: Record<MainContentMaxWidth, string> = {
  none: '',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-xl',
  '2xl': 'max-w-2xl',
  '4xl': 'max-w-4xl',
  '6xl': 'max-w-6xl',
  '7xl': 'max-w-7xl',
};

export function MainContent({
  breadcrumbs,
  header,
  children,
  maxWidth = '7xl',
  className,
}: MainContentProps) {
  return (
    <div
      data-arqel-main=""
      className={cn(
        'mx-auto w-full px-4 py-6 sm:px-6 lg:px-8',
        MAX_WIDTH_CLASSES[maxWidth],
        className,
      )}
    >
      {breadcrumbs && <div className="mb-3">{breadcrumbs}</div>}
      {header && <div className="mb-6">{header}</div>}
      {children}
    </div>
  );
}

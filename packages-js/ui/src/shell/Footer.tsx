/**
 * `<Footer>` — minimal optional footer rendered below `<MainContent>`.
 */

import type { ReactNode } from 'react';
import { cn } from '../utils/cn.js';

export interface FooterProps {
  children: ReactNode;
  className?: string;
}

export function Footer({ children, className }: FooterProps) {
  return (
    <footer
      data-arqel-footer=""
      className={cn(
        'shrink-0 border-t border-border bg-background px-4 py-3 text-sm text-muted-foreground',
        className,
      )}
    >
      {children}
    </footer>
  );
}

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
        'shrink-0 border-t border-[var(--color-arqel-border)] bg-[var(--color-arqel-bg)] px-4 py-3 text-sm text-[var(--color-arqel-muted-fg)]',
        className,
      )}
    >
      {children}
    </footer>
  );
}

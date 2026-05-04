/**
 * `<Topbar>` — header strip above the main content.
 *
 * Phase 1 wires the brand slot, theme toggle, and a mobile menu trigger.
 * Search and tenant switcher are reserved slots for Phase 2 (command
 * palette + multi-tenancy). The user menu is exposed as a slot so apps
 * can plug in any dropdown impl (Base UI Menu, ShadCN-generated, etc.).
 */

import { useTheme } from '@arqel-dev/react/providers';
import type { ReactNode } from 'react';
import { Button } from '../action/Button.js';
import { cn } from '../utils/cn.js';

export interface TopbarProps {
  brand?: ReactNode;
  search?: ReactNode;
  userMenu?: ReactNode;
  tenantSwitcher?: ReactNode;
  onMobileMenuClick?: () => void;
  className?: string;
}

export function Topbar({
  brand,
  search,
  userMenu,
  tenantSwitcher,
  onMobileMenuClick,
  className,
}: TopbarProps) {
  const { resolved, toggle } = useTheme();

  return (
    <header
      data-arqel-topbar=""
      className={cn(
        'flex h-14 shrink-0 items-center gap-2 border-b border-[var(--color-arqel-border)] bg-[var(--color-arqel-bg)] px-4',
        className,
      )}
    >
      {onMobileMenuClick && (
        <Button
          variant="ghost"
          size="icon"
          aria-label="Open navigation"
          className="md:hidden"
          onClick={onMobileMenuClick}
        >
          <span aria-hidden="true">≡</span>
        </Button>
      )}
      {brand && <div className="flex items-center">{brand}</div>}
      <div className="hidden flex-1 md:block">{search}</div>
      <div className="ml-auto flex items-center gap-2 md:ml-0">
        {tenantSwitcher}
        <Button
          variant="ghost"
          size="icon"
          aria-label={`Switch to ${resolved === 'dark' ? 'light' : 'dark'} theme`}
          onClick={toggle}
        >
          <span aria-hidden="true">{resolved === 'dark' ? '☀' : '☾'}</span>
        </Button>
        {userMenu}
      </div>
    </header>
  );
}

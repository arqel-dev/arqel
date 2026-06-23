/**
 * `<Topbar>` — header strip above the main content.
 *
 * Phase 1 wires the brand slot, theme toggle, and a mobile menu trigger.
 * Search and tenant switcher are reserved slots for Phase 2 (command
 * palette + multi-tenancy). The user menu is exposed as a slot so apps
 * can plug in any dropdown impl (Base UI Menu, ShadCN-generated, etc.).
 */

import { useTheme } from '@arqel-dev/react/providers';
import { useArqelTranslations } from '@arqel-dev/react/utils';
import type { ReactNode } from 'react';
import { Button } from '../action/Button.js';
import { SidebarTrigger } from '../shadcn/ui/sidebar.js';
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
  const t = useArqelTranslations();

  return (
    <header
      data-arqel-topbar=""
      className={cn(
        // `min-w-0 overflow-hidden` keep the topbar from ever pushing the body
        // wide: as a flex child it must be allowed to shrink below its content
        // (min-w-0), and any control cluster that still can't fit is clipped
        // here instead of overflowing the viewport. Radix popovers (tenant /
        // locale / user menus) render in a portal, so clipping the bar never
        // hides their open content.
        'flex h-14 min-w-0 shrink-0 items-center gap-2 overflow-hidden border-b border-border bg-background px-4',
        className,
      )}
    >
      <SidebarTrigger className="-ml-1" />
      {brand && <div className="flex min-w-0 items-center">{brand}</div>}
      <div className="hidden min-w-0 flex-1 md:block">{search}</div>
      <div className="ml-auto flex min-w-0 items-center gap-2 md:ml-0">
        {tenantSwitcher}
        <Button
          variant="ghost"
          size="icon"
          aria-label={
            resolved === 'dark'
              ? t('arqel.aria.theme_toggle_light', 'Switch to light theme')
              : t('arqel.aria.theme_toggle_dark', 'Switch to dark theme')
          }
          onClick={toggle}
        >
          <span aria-hidden="true">{resolved === 'dark' ? '☀' : '☾'}</span>
        </Button>
        {userMenu}
      </div>
    </header>
  );
}

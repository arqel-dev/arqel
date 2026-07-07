/**
 * `<Topbar>` — header strip above the main content.
 *
 * Phase 1 wires the brand slot and a mobile menu trigger. Search and
 * tenant switcher are reserved slots for Phase 2 (command palette +
 * multi-tenancy). The user menu (including theme control) is exposed as
 * a slot so apps can plug in any dropdown impl — see `<UserMenu>`.
 */

import type { ReactNode } from 'react';
import { SidebarTrigger } from '../shadcn/ui/sidebar.js';
import { cn } from '../utils/cn.js';

export interface TopbarProps {
  brand?: ReactNode;
  search?: ReactNode;
  notifications?: ReactNode;
  userMenu?: ReactNode;
  tenantSwitcher?: ReactNode;
  className?: string;
}

export function Topbar({
  brand,
  search,
  notifications,
  userMenu,
  tenantSwitcher,
  className,
}: TopbarProps) {
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
        {notifications}
        {userMenu}
      </div>
    </header>
  );
}

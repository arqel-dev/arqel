/**
 * `<Sidebar>` — primary navigation rail.
 *
 * Wraps the shadcn `Sidebar` block. Items come from `useNavigation()`
 * shared props (Inertia panel.navigation) by default, but the component
 * also accepts an `items` override for custom panels. The shadcn block
 * already handles desktop rail, mobile sheet, collapsed/icon mode, and
 * keyboard/cookie state via `<SidebarProvider>` (rendered by `<AppShell>`).
 */

import { type NavigationItemPayload, useNavigation } from '@arqel-dev/hooks';
import { type ReactNode, useMemo } from 'react';
import {
  Sidebar as ShadcnSidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '../shadcn/ui/sidebar.js';

export interface SidebarProps {
  items?: NavigationItemPayload[];
  brand?: ReactNode;
  footer?: ReactNode;
  className?: string;
}

export function Sidebar({ items: itemsProp, brand, footer, className }: SidebarProps) {
  const { items: sharedItems } = useNavigation();
  const items = itemsProp ?? sharedItems;
  const grouped = useMemo(() => groupItems(items), [items]);

  return (
    <ShadcnSidebar collapsible="icon" className={className}>
      {brand ? (
        <SidebarHeader>
          <div className="flex items-center gap-2 px-2 py-1.5 text-sm font-semibold">{brand}</div>
        </SidebarHeader>
      ) : null}
      <SidebarContent>
        {grouped.map(([group, list]) => (
          <SidebarGroup key={group ?? '__ungrouped__'}>
            {group ? <SidebarGroupLabel>{group}</SidebarGroupLabel> : null}
            <SidebarGroupContent>
              <SidebarMenu>
                {list.map((item) => (
                  <SidebarMenuItem key={item.url ?? item.label}>
                    <SidebarMenuButton asChild={Boolean(item.url)} isActive={Boolean(item.active)}>
                      {item.url ? (
                        <a href={item.url}>
                          <span>{item.label}</span>
                        </a>
                      ) : (
                        <span>{item.label}</span>
                      )}
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                ))}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        ))}
      </SidebarContent>
      {footer ? <SidebarFooter>{footer}</SidebarFooter> : null}
    </ShadcnSidebar>
  );
}

function groupItems(
  items: NavigationItemPayload[],
): Array<[string | undefined, NavigationItemPayload[]]> {
  const groups = new Map<string | undefined, NavigationItemPayload[]>();
  for (const item of items) {
    const key = item.group ?? undefined;
    const list = groups.get(key) ?? [];
    list.push(item);
    groups.set(key, list);
  }
  return Array.from(groups.entries());
}

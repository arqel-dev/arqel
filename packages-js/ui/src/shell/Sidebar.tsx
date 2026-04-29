/**
 * `<Sidebar>` — primary navigation rail.
 *
 * Desktop: fixed-width (240px default, override via `--sidebar-width`).
 * Mobile (`md:` breakpoint): rendered inside a Base UI `Dialog` overlay
 * driven by the `open` / `onOpenChange` props. The default `useBreakpoint`
 * gate auto-collapses the in-flow rail at `<md` so callers can avoid
 * threading props through. Items come from `useNavigation()` shared props,
 * but the component also accepts an `items` override for custom panels.
 */

import { type NavigationItemPayload, useNavigation } from '@arqel/hooks';
import { Dialog } from '@base-ui-components/react/dialog';
import { useMemo } from 'react';
import { cn } from '../utils/cn.js';

export interface SidebarProps {
  items?: NavigationItemPayload[];
  brand?: React.ReactNode;
  footer?: React.ReactNode;
  open?: boolean;
  onOpenChange?: (open: boolean) => void;
  className?: string;
}

interface RenderProps {
  items: NavigationItemPayload[];
  brand?: React.ReactNode;
  footer?: React.ReactNode;
}

function SidebarBody({ items, brand, footer }: RenderProps) {
  const grouped = useMemo(() => groupItems(items), [items]);

  return (
    <div className="flex h-full flex-col">
      {brand && (
        <div className="flex h-14 shrink-0 items-center border-b border-[var(--color-arqel-border)] px-4">
          {brand}
        </div>
      )}
      <nav className="flex-1 overflow-y-auto p-2" aria-label="Primary">
        {grouped.map(([group, list]) => (
          <div key={group ?? '__ungrouped__'} className="mb-4">
            {group && (
              <div className="px-2 pb-1 text-xs font-semibold uppercase tracking-wide text-[var(--color-arqel-muted-fg)]">
                {group}
              </div>
            )}
            <ul className="space-y-0.5">
              {list.map((item) => (
                <SidebarItem key={`${group ?? ''}::${item.url}::${item.label}`} item={item} />
              ))}
            </ul>
          </div>
        ))}
      </nav>
      {footer && (
        <div className="shrink-0 border-t border-[var(--color-arqel-border)] p-2">{footer}</div>
      )}
    </div>
  );
}

function SidebarItem({ item }: { item: NavigationItemPayload }) {
  return (
    <li>
      <a
        href={item.url}
        aria-current={item.active ? 'page' : undefined}
        className={cn(
          'flex items-center gap-2 rounded-[var(--radius-arqel-sm)] px-3 py-2 text-sm transition-colors',
          'hover:bg-[var(--color-arqel-muted)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)]',
          item.active && 'bg-[var(--color-arqel-muted)] font-medium',
        )}
      >
        <span className="flex-1 truncate">{item.label}</span>
        {item.badge !== undefined && item.badge !== null && (
          <span className="rounded-full bg-[var(--color-arqel-primary)] px-2 py-0.5 text-xs text-[var(--color-arqel-primary-fg)]">
            {item.badge}
          </span>
        )}
      </a>
    </li>
  );
}

function groupItems(
  items: NavigationItemPayload[],
): Array<[string | null, NavigationItemPayload[]]> {
  const map = new Map<string | null, NavigationItemPayload[]>();
  for (const item of items) {
    const key = item.group ?? null;
    const list = map.get(key);
    if (list) list.push(item);
    else map.set(key, [item]);
  }
  return Array.from(map.entries());
}

export function Sidebar({ items, brand, footer, open, onOpenChange, className }: SidebarProps) {
  const fromHook = useNavigation();
  const resolvedItems = items ?? fromHook.items;

  const desktop = (
    <aside
      data-arqel-sidebar=""
      style={{ width: 'var(--sidebar-width, 240px)' }}
      className={cn(
        'hidden shrink-0 border-r border-[var(--color-arqel-border)] bg-[var(--color-arqel-bg)] md:block',
        className,
      )}
    >
      <SidebarBody items={resolvedItems} brand={brand} footer={footer} />
    </aside>
  );

  if (open === undefined) return desktop;

  return (
    <>
      {desktop}
      <Dialog.Root open={open} onOpenChange={(next) => onOpenChange?.(next)} modal>
        <Dialog.Portal>
          <Dialog.Backdrop className="fixed inset-0 z-40 bg-black/40 md:hidden" />
          <Dialog.Popup
            aria-label="Navigation"
            className={cn(
              'fixed inset-y-0 left-0 z-50 w-72 border-r border-[var(--color-arqel-border)] bg-[var(--color-arqel-bg)] shadow-xl outline-none md:hidden',
              className,
            )}
          >
            <SidebarBody items={resolvedItems} brand={brand} footer={footer} />
          </Dialog.Popup>
        </Dialog.Portal>
      </Dialog.Root>
    </>
  );
}

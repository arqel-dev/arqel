/**
 * `<NotificationList>` — renders the `recent` notifications inside the
 * `<NotificationBell>` dropdown content.
 *
 * Extracted from `NotificationBell` so it can be exercised directly in
 * tests without depending on Radix `DropdownMenu` mounting its portal
 * content in jsdom.
 */

import type { NotificationItem } from '@arqel-dev/types/inertia';
import { Link } from '@inertiajs/react';
import { AlertTriangle, Bell, Check, Info, type LucideIcon, Mail, User } from 'lucide-react';
import type { JSX } from 'react';
import { cn } from '../utils/cn.js';
import { isSameOriginRelativeUrl } from '../utils/url.js';

export interface NotificationListProps {
  items: ReadonlyArray<NotificationItem>;
  emptyLabel: string;
  onItemClick: (item: NotificationItem) => void;
}

/**
 * Allowlist of `data.icon` values accepted from notification payloads.
 *
 * Only these lucide-react icons may be rendered — no dynamic/arbitrary
 * lookup into the lucide namespace, to keep the bundle small and avoid
 * exposing an unbounded icon surface to server-controlled data.
 *
 * Supported keys: `bell` (default), `check`, `info`, `alert`, `mail`, `user`.
 */
const NOTIFICATION_ICONS: Record<string, LucideIcon> = {
  bell: Bell,
  check: Check,
  info: Info,
  alert: AlertTriangle,
  mail: Mail,
  user: User,
};

function readTitle(item: NotificationItem): string {
  const title = item.data['title'];
  if (typeof title === 'string' && title.trim()) return title;
  return item.type;
}

function readBody(item: NotificationItem): string | undefined {
  const body = item.data['body'] ?? item.data['message'];
  return typeof body === 'string' && body.trim() ? body : undefined;
}

/**
 * Only same-origin relative URLs (`/foo`, never `//evil.com`,
 * `https://…`, or `javascript:…`) unlock a navigable `Link` — mirrors
 * the `data.icon` allowlist below: server-controlled notification
 * payloads get a narrow, safe subset of behavior, never arbitrary
 * navigation. Anything else renders the item as a non-navigable button.
 */
function readActionUrl(item: NotificationItem): string | undefined {
  const url = item.data['action_url'];
  return typeof url === 'string' && isSameOriginRelativeUrl(url) ? url : undefined;
}

function readIcon(item: NotificationItem): LucideIcon {
  const icon = item.data['icon'];
  if (typeof icon === 'string' && icon in NOTIFICATION_ICONS) {
    return NOTIFICATION_ICONS[icon] ?? Bell;
  }
  return Bell;
}

export function NotificationList({
  items,
  emptyLabel,
  onItemClick,
}: NotificationListProps): JSX.Element {
  if (items.length === 0) {
    return <div className="px-2 py-4 text-center text-sm text-muted-foreground">{emptyLabel}</div>;
  }

  return (
    <div className="flex max-h-80 flex-col gap-0.5 overflow-y-auto">
      {items.map((item) => {
        const title = readTitle(item);
        const body = readBody(item);
        const actionUrl = readActionUrl(item);
        const unread = item.read_at === null;
        const Icon = readIcon(item);

        const itemContent = (
          <>
            <span
              aria-hidden="true"
              className={cn(
                'mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full',
                unread ? 'bg-primary' : 'bg-transparent',
              )}
            />
            <Icon aria-hidden="true" className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
            <span className="flex min-w-0 flex-col">
              <span className="truncate font-medium">{title}</span>
              {body && <span className="truncate text-xs text-muted-foreground">{body}</span>}
            </span>
          </>
        );
        const itemClassName = cn(
          'flex w-full items-start gap-2 rounded-sm px-2 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground',
          unread && 'bg-accent/50',
        );

        // The item's root must be either a `Link` (navigable) or a
        // `button` (action-only) — never one nested inside the other,
        // which produces invalid `<a>`-inside-`<button>` HTML.
        return actionUrl ? (
          <Link
            key={item.id}
            href={actionUrl}
            onClick={() => onItemClick(item)}
            className={itemClassName}
          >
            {itemContent}
          </Link>
        ) : (
          <button
            key={item.id}
            type="button"
            onClick={() => onItemClick(item)}
            className={itemClassName}
          >
            {itemContent}
          </button>
        );
      })}
    </div>
  );
}

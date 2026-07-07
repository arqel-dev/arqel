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
import { Bell } from 'lucide-react';
import type { JSX } from 'react';
import { cn } from '../utils/cn.js';

export interface NotificationListProps {
  items: ReadonlyArray<NotificationItem>;
  emptyLabel: string;
  onItemClick: (item: NotificationItem) => void;
}

function readTitle(item: NotificationItem): string {
  const title = item.data['title'];
  if (typeof title === 'string' && title.trim()) return title;
  return item.type;
}

function readBody(item: NotificationItem): string | undefined {
  const body = item.data['body'] ?? item.data['message'];
  return typeof body === 'string' && body.trim() ? body : undefined;
}

function readActionUrl(item: NotificationItem): string | undefined {
  const url = item.data['action_url'];
  return typeof url === 'string' && url ? url : undefined;
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

        const content = (
          <div
            className={cn(
              'flex w-full items-start gap-2 rounded-sm px-2 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground',
              unread && 'bg-accent/50',
            )}
          >
            <span
              aria-hidden="true"
              className={cn(
                'mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full',
                unread ? 'bg-primary' : 'bg-transparent',
              )}
            />
            <Bell aria-hidden="true" className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
            <span className="flex min-w-0 flex-col">
              <span className="truncate font-medium">{title}</span>
              {body && <span className="truncate text-xs text-muted-foreground">{body}</span>}
            </span>
          </div>
        );

        return (
          <button
            key={item.id}
            type="button"
            onClick={() => onItemClick(item)}
            className="w-full text-left"
          >
            {actionUrl ? (
              <Link href={actionUrl} className="block">
                {content}
              </Link>
            ) : (
              content
            )}
          </button>
        );
      })}
    </div>
  );
}

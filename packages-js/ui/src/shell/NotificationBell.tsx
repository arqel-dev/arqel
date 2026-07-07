/**
 * `<NotificationBell>` — database-notifications trigger for the Topbar
 * `notifications` slot.
 *
 * Reads the shared `notifications` prop (`NotificationPayload | null`,
 * see `HandleArqelInertiaRequests`) via `usePage()`. Mounts a bell icon
 * with an unread-count `Badge` (shown only when `unread_count > 0`) and
 * a `DropdownMenu` listing the `recent` notifications. Marking a single
 * notification read, or all of them, does a scoped Inertia partial
 * reload (`only: ['notifications']`) — no full page reload.
 */

import { useArqelTranslations } from '@arqel-dev/react/utils';
import type { NotificationItem, NotificationPayload } from '@arqel-dev/types/inertia';
import { Link, router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import type { JSX } from 'react';
import { Badge } from '../shadcn/ui/badge.js';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '../shadcn/ui/dropdown-menu.js';
import { cn } from '../utils/cn.js';
import { NotificationList } from './NotificationList.js';

export interface NotificationBellProps {
  className?: string;
}

interface NotificationBellPageProps {
  notifications: NotificationPayload | null;
  [key: string]: unknown;
}

function markRead(id: string): void {
  router.post(
    `/admin/notifications/${id}/read`,
    {},
    { preserveScroll: true, only: ['notifications'] },
  );
}

function markAllRead(): void {
  router.post(
    '/admin/notifications/read-all',
    {},
    { preserveScroll: true, only: ['notifications'] },
  );
}

export function NotificationBell({ className }: NotificationBellProps): JSX.Element {
  const t = useArqelTranslations();
  const { notifications } = usePage<NotificationBellPageProps>().props;

  const unreadCount = notifications?.unread_count ?? 0;
  const recent = notifications?.recent ?? [];

  const handleItemClick = (item: NotificationItem): void => {
    if (item.read_at === null) markRead(item.id);
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        aria-label={t('arqel.notifications.title', 'Notifications')}
        className={cn(
          'relative inline-flex h-9 w-9 items-center justify-center rounded-md hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-ring',
          className,
        )}
      >
        <Bell aria-hidden="true" className="h-4 w-4 text-muted-foreground" />
        {unreadCount > 0 && (
          <Badge
            variant="destructive"
            className="absolute -right-1 -top-1 h-4 min-w-4 justify-center px-1 text-[10px] leading-none"
          >
            {unreadCount}
          </Badge>
        )}
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-80">
        <div className="flex items-center justify-between px-2 py-1.5">
          <DropdownMenuLabel className="p-0 font-medium">
            {t('arqel.notifications.title', 'Notifications')}
          </DropdownMenuLabel>
          {unreadCount > 0 && (
            <DropdownMenuItem
              className="w-auto justify-end p-0 text-xs text-muted-foreground hover:text-foreground"
              onSelect={(event) => {
                event.preventDefault();
                markAllRead();
              }}
            >
              {t('arqel.notifications.mark_all_read', 'Mark all as read')}
            </DropdownMenuItem>
          )}
        </div>

        <DropdownMenuSeparator />

        <NotificationList
          items={recent}
          emptyLabel={t('arqel.notifications.empty', 'No notifications')}
          onItemClick={handleItemClick}
        />

        <DropdownMenuSeparator />

        <DropdownMenuItem asChild className="justify-center text-sm">
          <Link href="/admin/notifications">{t('arqel.notifications.view_all', 'View all')}</Link>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

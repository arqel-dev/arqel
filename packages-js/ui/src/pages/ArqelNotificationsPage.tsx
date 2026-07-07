/**
 * Default Inertia page for `arqel::notifications`.
 *
 * Renders the paginated notification history emitted by
 * `Arqel\Notifications\Http\Controllers\NotificationController::index`
 * (`{ history: paginator, filter: 'all' | 'unread' }`). The page-prop
 * is named `history` — *not* `notifications` — because `notifications`
 * is already a shared Inertia prop (`{unread_count, recent}`, see
 * `HandleArqelInertiaRequests`) consumed by the `<NotificationBell>`
 * mounted on every page's topbar. Reusing that key here would let this
 * page's paginator silently overwrite the shared prop and break the
 * bell (see branch review, milestone 0.19).
 *
 * The paginator serializes each item as `{id, type, data, read_at,
 * created_at}` — same shape as the `notifications.recent` array the
 * `<NotificationBell>` dropdown consumes, so titles/bodies follow the
 * same `data.title` / `data.body` convention.
 *
 * Per-item actions do scoped Inertia visits (`only: ['history']` for
 * mark-as-read, a plain reload for delete) so the list refreshes
 * without a full page navigation.
 */

import { useArqelTranslations } from '@arqel-dev/react/utils';
import type { NotificationItem } from '@arqel-dev/types/inertia';
import { Link, router, usePage } from '@inertiajs/react';
import type { JSX } from 'react';
import { Button } from '../action/Button.js';
import { Card } from '../shadcn/ui/card.js';
import { cn } from '../utils/cn.js';

interface LaravelPaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

interface NotificationsPaginator {
  data: NotificationItem[];
  links: LaravelPaginationLink[];
  meta: Record<string, unknown>;
}

interface ArqelNotificationsPageProps {
  history: NotificationsPaginator;
  filter: 'all' | 'unread';
  [key: string]: unknown;
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

function markRead(id: string): void {
  router.post(
    `/admin/notifications/${id}/read`,
    {},
    { preserveScroll: true, only: ['history', 'notifications'] },
  );
}

function markAllRead(): void {
  router.post(
    '/admin/notifications/read-all',
    {},
    { preserveScroll: true, only: ['history', 'notifications'] },
  );
}

function destroy(id: string): void {
  router.delete(`/admin/notifications/${id}`, { preserveScroll: true });
}

/**
 * Laravel's paginator emits prev/next labels as HTML entities
 * (`&laquo; Previous`, `Next &raquo;`). Decode the small, known set we
 * expect rather than reaching for `dangerouslySetInnerHTML` — the
 * labels are server-controlled but there is no reason to accept
 * arbitrary markup here.
 */
function decodePaginationLabel(label: string): string {
  return label.replace(/&laquo;/g, '«').replace(/&raquo;/g, '»');
}

export default function ArqelNotificationsPage(): JSX.Element {
  const t = useArqelTranslations();
  const { history, filter } = usePage<ArqelNotificationsPageProps>().props;

  const items = history.data;
  const hasUnread = items.some((item) => item.read_at === null);

  return (
    <div className="flex flex-col gap-4 p-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h1 className="text-lg font-semibold">{t('arqel.notifications.title', 'Notifications')}</h1>
        <div className="flex items-center gap-2">
          <div className="flex items-center gap-1 text-sm">
            <Link
              href="/admin/notifications?filter=all"
              className={cn(
                'rounded-sm px-2 py-1',
                filter === 'all'
                  ? 'bg-accent font-medium text-accent-foreground'
                  : 'text-muted-foreground hover:text-foreground',
              )}
            >
              {t('arqel.notifications.filter_all', 'All')}
            </Link>
            <Link
              href="/admin/notifications?filter=unread"
              className={cn(
                'rounded-sm px-2 py-1',
                filter === 'unread'
                  ? 'bg-accent font-medium text-accent-foreground'
                  : 'text-muted-foreground hover:text-foreground',
              )}
            >
              {t('arqel.notifications.filter_unread', 'Unread')}
            </Link>
          </div>
          {hasUnread && (
            <Button variant="outline" size="sm" onClick={markAllRead}>
              {t('arqel.notifications.mark_all_read', 'Mark all as read')}
            </Button>
          )}
        </div>
      </div>

      {items.length === 0 ? (
        <Card className="p-6 text-center text-sm text-muted-foreground">
          {t('arqel.notifications.empty', 'No notifications')}
        </Card>
      ) : (
        <div className="flex flex-col gap-2">
          {items.map((item) => {
            const title = readTitle(item);
            const body = readBody(item);
            const unread = item.read_at === null;

            return (
              <Card
                key={item.id}
                className={cn('flex flex-row items-start justify-between gap-4 p-4', unread && 'bg-accent/50')}
              >
                <div className="flex min-w-0 flex-1 items-start gap-2">
                  <span
                    aria-hidden="true"
                    className={cn(
                      'mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full',
                      unread ? 'bg-primary' : 'bg-transparent',
                    )}
                  />
                  <div className="flex min-w-0 flex-col">
                    <span className="font-medium">{title}</span>
                    {body && <span className="text-sm text-muted-foreground">{body}</span>}
                    <span className="text-xs text-muted-foreground">{item.created_at}</span>
                  </div>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                  {unread && (
                    <Button variant="outline" size="sm" onClick={() => markRead(item.id)}>
                      {t('arqel.notifications.mark_read', 'Mark as read')}
                    </Button>
                  )}
                  <Button variant="ghost" size="sm" onClick={() => destroy(item.id)}>
                    {t('arqel.notifications.delete', 'Delete')}
                  </Button>
                </div>
              </Card>
            );
          })}
        </div>
      )}

      {history.links.length > 0 && (
        <nav
          className="flex flex-wrap items-center gap-1 text-sm"
          aria-label={t('table.pagination.label', 'Pagination')}
        >
          {history.links.map((link, index) =>
            link.url ? (
              <Link
                key={`${link.label}-${index}`}
                href={link.url}
                className={cn(
                  'rounded-sm px-2 py-1',
                  link.active
                    ? 'bg-accent font-medium text-accent-foreground'
                    : 'text-muted-foreground hover:text-foreground',
                )}
              >
                {decodePaginationLabel(link.label)}
              </Link>
            ) : (
              <span
                key={`${link.label}-${index}`}
                className="rounded-sm px-2 py-1 text-muted-foreground/50"
              >
                {decodePaginationLabel(link.label)}
              </span>
            ),
          )}
        </nav>
      )}
    </div>
  );
}

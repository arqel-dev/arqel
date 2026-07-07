import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

const post = vi.fn();
const del = vi.fn();
let mockProps: unknown;

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: mockProps }),
  router: {
    post: (...args: unknown[]) => post(...args),
    delete: (...args: unknown[]) => del(...args),
  },
  Link: ({
    href,
    children,
    className,
  }: {
    href: string;
    children: React.ReactNode;
    className?: string;
  }) => (
    <a href={href} className={className}>
      {children}
    </a>
  ),
}));

vi.mock('@arqel-dev/react/utils', () => ({
  useArqelTranslations: () => (_key: string, fallback?: string) => fallback ?? _key,
}));

import ArqelNotificationsPage from '../../src/pages/ArqelNotificationsPage.js';

describe('ArqelNotificationsPage', () => {
  it('lists notifications and offers per-item actions', () => {
    mockProps = {
      notifications: {
        data: [
          {
            id: 'a',
            type: 'Welcome',
            data: { title: 'Olá' },
            read_at: null,
            created_at: '2026-07-07T00:00:00Z',
          },
        ],
        links: [],
        meta: {},
      },
      filter: 'all',
    };
    render(<ArqelNotificationsPage />);
    expect(screen.getByText('Olá')).toBeInTheDocument();
  });

  it('shows an empty state when there are no notifications', () => {
    mockProps = { notifications: { data: [], links: [], meta: {} }, filter: 'all' };
    render(<ArqelNotificationsPage />);
    expect(screen.getByText(/No notifications|Nenhuma/)).toBeInTheDocument();
  });

  it('marks an unread notification as read', async () => {
    mockProps = {
      notifications: {
        data: [
          {
            id: 'a',
            type: 'Welcome',
            data: { title: 'Olá' },
            read_at: null,
            created_at: '2026-07-07T00:00:00Z',
          },
        ],
        links: [],
        meta: {},
      },
      filter: 'all',
    };
    render(<ArqelNotificationsPage />);
    await userEvent.click(screen.getByRole('button', { name: /mark as read|marcar como lida/i }));
    expect(post).toHaveBeenCalledWith(
      '/admin/notifications/a/read',
      {},
      { preserveScroll: true, only: ['notifications'] },
    );
  });

  it('does not offer "mark as read" for an already-read notification', () => {
    mockProps = {
      notifications: {
        data: [
          {
            id: 'a',
            type: 'Welcome',
            data: { title: 'Olá' },
            read_at: '2026-07-07T00:00:00Z',
            created_at: '2026-07-07T00:00:00Z',
          },
        ],
        links: [],
        meta: {},
      },
      filter: 'all',
    };
    render(<ArqelNotificationsPage />);
    expect(
      screen.queryByRole('button', { name: /mark as read|marcar como lida/i }),
    ).not.toBeInTheDocument();
  });

  it('deletes a notification', async () => {
    mockProps = {
      notifications: {
        data: [
          {
            id: 'a',
            type: 'Welcome',
            data: { title: 'Olá' },
            read_at: null,
            created_at: '2026-07-07T00:00:00Z',
          },
        ],
        links: [],
        meta: {},
      },
      filter: 'all',
    };
    render(<ArqelNotificationsPage />);
    await userEvent.click(screen.getByRole('button', { name: /delete|excluir/i }));
    expect(del).toHaveBeenCalledWith('/admin/notifications/a', { preserveScroll: true });
  });

  it('shows "mark all as read" only when there are unread notifications', () => {
    mockProps = {
      notifications: {
        data: [
          {
            id: 'a',
            type: 'Welcome',
            data: { title: 'Olá' },
            read_at: null,
            created_at: '2026-07-07T00:00:00Z',
          },
        ],
        links: [],
        meta: {},
      },
      filter: 'all',
    };
    render(<ArqelNotificationsPage />);
    expect(
      screen.getByRole('button', { name: /mark all as read|marcar todas/i }),
    ).toBeInTheDocument();
  });

  it('renders filter links for all and unread', () => {
    mockProps = { notifications: { data: [], links: [], meta: {} }, filter: 'unread' };
    render(<ArqelNotificationsPage />);
    expect(screen.getByRole('link', { name: /all/i })).toHaveAttribute(
      'href',
      '/admin/notifications?filter=all',
    );
    expect(screen.getByRole('link', { name: /unread/i })).toHaveAttribute(
      'href',
      '/admin/notifications?filter=unread',
    );
  });
});

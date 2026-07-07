import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

const post = vi.fn();
let mockProps: unknown;
vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: mockProps }),
  router: { post: (...args: unknown[]) => post(...args) },
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => (
    <a href={href}>{children}</a>
  ),
}));

vi.mock('@arqel-dev/react/utils', () => ({
  useArqelTranslations: () => (_key: string, fallback?: string) => fallback ?? _key,
}));

import { NotificationBell } from '../src/shell/NotificationBell.js';

describe('NotificationBell', () => {
  it('shows the unread badge when there are unread notifications', () => {
    mockProps = { notifications: { unread_count: 3, recent: [] } };
    render(<NotificationBell />);
    expect(screen.getByText('3')).toBeInTheDocument();
  });

  it('hides the badge when unread_count is 0', () => {
    mockProps = { notifications: { unread_count: 0, recent: [] } };
    render(<NotificationBell />);
    expect(screen.queryByText('0')).not.toBeInTheDocument();
  });

  it('renders a notification title from data and marks it read on click', async () => {
    mockProps = {
      notifications: {
        unread_count: 1,
        recent: [
          {
            id: 'abc',
            type: 'Welcome',
            data: { title: 'Olá', action_url: '/x' },
            read_at: null,
            created_at: '2026-07-07T00:00:00Z',
          },
        ],
      },
    };
    render(<NotificationBell />);
    await userEvent.click(screen.getByRole('button', { name: /notifications/i }));
    expect(screen.getByText('Olá')).toBeInTheDocument();

    await userEvent.click(screen.getByText('Olá'));
    expect(post).toHaveBeenCalledWith(
      '/admin/notifications/abc/read',
      {},
      { preserveScroll: true, only: ['notifications'] },
    );
  });

  it('renders a graceful fallback when data has no convention keys', async () => {
    mockProps = {
      notifications: {
        unread_count: 1,
        recent: [
          {
            id: 'z',
            type: 'RawThing',
            data: { foo: 1 },
            read_at: null,
            created_at: '2026-07-07T00:00:00Z',
          },
        ],
      },
    };
    render(<NotificationBell />);
    await userEvent.click(screen.getByRole('button', { name: /notifications/i }));
    expect(screen.getByText(/RawThing/)).toBeInTheDocument();
  });

  it('shows the empty state when there are no recent notifications', async () => {
    mockProps = { notifications: { unread_count: 0, recent: [] } };
    render(<NotificationBell />);
    await userEvent.click(screen.getByRole('button', { name: /notifications/i }));
    expect(screen.getByText(/no notifications/i)).toBeInTheDocument();
  });

  it('marks all as read when the header action is clicked', async () => {
    mockProps = {
      notifications: {
        unread_count: 2,
        recent: [
          {
            id: 'a',
            type: 'X',
            data: { title: 'A' },
            read_at: null,
            created_at: '2026-07-07T00:00:00Z',
          },
        ],
      },
    };
    render(<NotificationBell />);
    await userEvent.click(screen.getByRole('button', { name: /notifications/i }));
    await userEvent.click(screen.getByText(/mark all as read/i));
    expect(post).toHaveBeenCalledWith(
      '/admin/notifications/read-all',
      {},
      { preserveScroll: true, only: ['notifications'] },
    );
  });

  it('renders nothing special when notifications prop is null', () => {
    mockProps = { notifications: null };
    render(<NotificationBell />);
    expect(screen.queryByText(/\d+/)).not.toBeInTheDocument();
  });
});

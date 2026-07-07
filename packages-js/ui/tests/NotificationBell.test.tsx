import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

const post = vi.fn();
let mockProps: unknown;
vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: mockProps }),
  router: { post: (...args: unknown[]) => post(...args) },
  Link: ({
    href,
    children,
    className,
    onClick,
  }: {
    href: string;
    children: React.ReactNode;
    className?: string;
    onClick?: (event: React.MouseEvent<HTMLAnchorElement>) => void;
  }) => (
    // Real Inertia `Link` intercepts the click and never lets the browser
    // navigate; mimic that here so jsdom doesn't attempt a real navigation.
    <a
      href={href}
      className={className}
      onClick={(event) => {
        event.preventDefault();
        onClick?.(event);
      }}
    >
      {children}
    </a>
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
            data: { title: 'Olá' },
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

  it('renders the item as a navigable link when action_url is present, and still marks it read', async () => {
    mockProps = {
      notifications: {
        unread_count: 1,
        recent: [
          {
            id: 'lnk',
            type: 'Welcome',
            data: { title: 'Go somewhere', action_url: '/x' },
            read_at: null,
            created_at: '2026-07-07T00:00:00Z',
          },
        ],
      },
    };
    render(<NotificationBell />);
    await userEvent.click(screen.getByRole('button', { name: /notifications/i }));

    const link = screen.getByRole('link', { name: /go somewhere/i });
    expect(link).toHaveAttribute('href', '/x');
    // The link must not be nested inside a <button> (invalid HTML).
    expect(link.closest('button')).toBeNull();

    await userEvent.click(link);
    expect(post).toHaveBeenCalledWith(
      '/admin/notifications/lnk/read',
      {},
      { preserveScroll: true, only: ['notifications'] },
    );
  });

  it('respects a known data.icon value instead of the default Bell icon', async () => {
    mockProps = {
      notifications: {
        unread_count: 1,
        recent: [
          {
            id: 'ic',
            type: 'Welcome',
            data: { title: 'Checked', icon: 'check' },
            read_at: null,
            created_at: '2026-07-07T00:00:00Z',
          },
        ],
      },
    };
    render(<NotificationBell />);
    await userEvent.click(screen.getByRole('button', { name: /notifications/i }));
    const itemText = screen.getByText('Checked');

    // lucide-react icons render an <svg class="lucide-check ...">; scope the
    // lookup to the notification item (the trigger also renders a Bell icon).
    const item = itemText.closest('button, a');
    expect(item?.querySelector('svg.lucide-check')).not.toBeNull();
    expect(item?.querySelector('svg.lucide-bell')).toBeNull();
  });

  it('falls back to the Bell icon for an unknown data.icon value', async () => {
    mockProps = {
      notifications: {
        unread_count: 1,
        recent: [
          {
            id: 'ic2',
            type: 'Welcome',
            data: { title: 'Mystery', icon: 'not-a-real-icon' },
            read_at: null,
            created_at: '2026-07-07T00:00:00Z',
          },
        ],
      },
    };
    render(<NotificationBell />);
    await userEvent.click(screen.getByRole('button', { name: /notifications/i }));
    const item = screen.getByText('Mystery').closest('button, a');
    expect(item?.querySelector('svg.lucide-bell')).not.toBeNull();
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

  it('does not render the item as a Link when action_url points off-site', async () => {
    mockProps = {
      notifications: {
        unread_count: 1,
        recent: [
          {
            id: 'evil',
            type: 'Welcome',
            data: { title: 'Suspicious', action_url: 'https://evil.com' },
            read_at: null,
            created_at: '2026-07-07T00:00:00Z',
          },
        ],
      },
    };
    render(<NotificationBell />);
    await userEvent.click(screen.getByRole('button', { name: /notifications/i }));
    expect(screen.queryByRole('link', { name: /suspicious/i })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: /suspicious/i })).toBeInTheDocument();
  });

  it('renders the item as a Link when action_url is same-origin relative', async () => {
    mockProps = {
      notifications: {
        unread_count: 1,
        recent: [
          {
            id: 'safe',
            type: 'Welcome',
            data: { title: 'Go admin', action_url: '/admin/x' },
            read_at: null,
            created_at: '2026-07-07T00:00:00Z',
          },
        ],
      },
    };
    render(<NotificationBell />);
    await userEvent.click(screen.getByRole('button', { name: /notifications/i }));
    const link = screen.getByRole('link', { name: /go admin/i });
    expect(link).toHaveAttribute('href', '/admin/x');
  });

  it('reads unread_count/recent from the shared notifications prop even when a different history page-prop is present', () => {
    mockProps = {
      notifications: { unread_count: 2, recent: [] },
      history: {
        data: [
          {
            id: 'h1',
            type: 'Welcome',
            data: { title: 'From history' },
            read_at: null,
            created_at: '2026-07-07T00:00:00Z',
          },
        ],
        links: [],
        meta: {},
      },
    };
    render(<NotificationBell />);
    expect(screen.getByText('2')).toBeInTheDocument();
  });
});

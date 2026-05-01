import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { type NavigationSnapshot, TimeTravel } from '../TimeTravel';

function makeSnapshots(): NavigationSnapshot[] {
  return [
    {
      id: 'snap-3',
      timestamp: 1_700_000_002_000,
      url: '/admin/posts',
      pageProps: { posts: [{ id: 9, title: 'Hello' }] },
      sharedProps: { auth: { id: 1 } },
      durationMs: 42,
    },
    {
      id: 'snap-2',
      timestamp: 1_700_000_001_000,
      url: '/admin/users/1',
      pageProps: { user: { id: 1, name: 'Ada' } },
      sharedProps: {},
      durationMs: 180,
    },
    {
      id: 'snap-1',
      timestamp: 1_700_000_000_000,
      url: '/admin/users',
      pageProps: { users: [] },
      sharedProps: {},
    },
  ];
}

describe('<TimeTravel />', () => {
  it('renders one entry per snapshot in the supplied order', () => {
    render(<TimeTravel snapshots={makeSnapshots()} />);
    const rows = screen.getAllByTestId('time-travel-entry');
    expect(rows).toHaveLength(3);
    expect(rows[0]).toHaveAttribute('data-snapshot-id', 'snap-3');
    expect(rows[1]).toHaveAttribute('data-snapshot-id', 'snap-2');
    expect(rows[2]).toHaveAttribute('data-snapshot-id', 'snap-1');
    expect(screen.getByTestId('time-travel-counter')).toHaveTextContent('3 snapshots');
    // Slow snapshot (duration >= 100ms) flagged.
    expect(rows[1]).toHaveAttribute('data-slow', 'true');
    expect(rows[0]).toHaveAttribute('data-slow', 'false');
  });

  it('expands pageProps when an entry is clicked and collapses on second click', () => {
    render(<TimeTravel snapshots={makeSnapshots()} />);
    expect(screen.queryByTestId('time-travel-detail')).not.toBeInTheDocument();
    const toggles = screen.getAllByTestId('time-travel-toggle');
    fireEvent.click(toggles[1] as HTMLElement);
    const detail = screen.getByTestId('time-travel-detail');
    expect(detail).toBeInTheDocument();
    expect(detail.textContent).toContain('user');
    expect(detail.textContent).toContain('sharedProps');
    fireEvent.click(toggles[1] as HTMLElement);
    expect(screen.queryByTestId('time-travel-detail')).not.toBeInTheDocument();
  });

  it('fires the replay handler with the corresponding snapshot', () => {
    const onReplay = vi.fn();
    render(<TimeTravel snapshots={makeSnapshots()} onReplay={onReplay} />);
    const buttons = screen.getAllByTestId('time-travel-replay');
    fireEvent.click(buttons[0] as HTMLElement);
    expect(onReplay).toHaveBeenCalledTimes(1);
    expect(onReplay.mock.calls[0]?.[0]).toMatchObject({ id: 'snap-3', url: '/admin/posts' });
  });

  it('renders an empty state when no snapshots have been captured', () => {
    render(<TimeTravel snapshots={[]} />);
    expect(screen.getByTestId('time-travel-empty')).toHaveTextContent(
      'No navigation snapshots captured yet',
    );
    expect(screen.queryByTestId('time-travel-list')).not.toBeInTheDocument();
  });
});

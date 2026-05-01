import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { type ArqelState, InertiaInspector } from '../InertiaInspector';

function makeState(overrides: Partial<ArqelState> = {}): ArqelState {
  return {
    panel: 'admin',
    resource: 'users',
    pageProps: { users: [{ id: 1, name: 'Ada' }] },
    sharedProps: { auth: { id: 7 } },
    currentPath: '/admin/users',
    navigationHistory: [
      { path: '/admin/users', timestamp: 1_700_000_000_000, durationMs: 42 },
      { path: '/admin/posts', timestamp: 1_700_000_001_000 },
    ],
    ...overrides,
  };
}

function staticSource(state: ArqelState) {
  return (cb: (s: ArqelState) => void): (() => void) => {
    cb(state);
    return () => {};
  };
}

describe('<InertiaInspector />', () => {
  it('renders three tablist buttons and defaults to Page Props', () => {
    render(<InertiaInspector stateSource={staticSource(makeState())} />);
    expect(screen.getByTestId('tab-page-props')).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByTestId('tab-shared-props')).toHaveAttribute('aria-selected', 'false');
    expect(screen.getByTestId('tab-navigation')).toHaveAttribute('aria-selected', 'false');
    // pageProps default-expanded shows nested users array.
    expect(screen.getByTestId('json-node-pageProps')).toBeInTheDocument();
  });

  it('switches tabs when buttons are clicked', () => {
    render(<InertiaInspector stateSource={staticSource(makeState())} />);
    fireEvent.click(screen.getByTestId('tab-shared-props'));
    expect(screen.getByTestId('tab-shared-props')).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByTestId('json-node-sharedProps')).toBeInTheDocument();

    fireEvent.click(screen.getByTestId('tab-navigation'));
    expect(screen.getByTestId('navigation-list')).toBeInTheDocument();
    expect(screen.getAllByTestId('navigation-entry')).toHaveLength(2);
  });

  it('filters navigation history by search term', () => {
    render(<InertiaInspector stateSource={staticSource(makeState())} />);
    fireEvent.click(screen.getByTestId('tab-navigation'));
    fireEvent.change(screen.getByTestId('inspector-search'), { target: { value: 'posts' } });
    const entries = screen.getAllByTestId('navigation-entry');
    expect(entries).toHaveLength(1);
    expect(entries[0]).toHaveTextContent('/admin/posts');
  });

  it('filters JSON tree by highlighting matches', () => {
    render(<InertiaInspector stateSource={staticSource(makeState())} />);
    fireEvent.change(screen.getByTestId('inspector-search'), { target: { value: 'Ada' } });
    expect(screen.getByTestId('json-node-pageProps')).toHaveAttribute('data-match', 'yes');
  });

  it('copies JSON state when Copy button is clicked', async () => {
    const writeClipboard = vi.fn().mockResolvedValue(undefined);
    const state = makeState();
    render(<InertiaInspector stateSource={staticSource(state)} writeClipboard={writeClipboard} />);
    fireEvent.click(screen.getByTestId('inspector-copy'));
    await waitFor(() => {
      expect(writeClipboard).toHaveBeenCalledTimes(1);
    });
    const payload = writeClipboard.mock.calls[0]?.[0] as string;
    expect(JSON.parse(payload)).toEqual(state);
    await waitFor(() => {
      expect(screen.getByTestId('inspector-copy')).toHaveTextContent('Copied!');
    });
  });

  it('subscribes to stateSource updates and rerenders', () => {
    let push: (s: ArqelState) => void = () => {};
    const source = (cb: (s: ArqelState) => void): (() => void) => {
      push = cb;
      cb(makeState({ navigationHistory: [] }));
      return () => {};
    };
    render(<InertiaInspector stateSource={source} />);
    fireEvent.click(screen.getByTestId('tab-navigation'));
    expect(screen.queryAllByTestId('navigation-entry')).toHaveLength(0);

    act(() => {
      push(makeState());
    });
    expect(screen.getAllByTestId('navigation-entry')).toHaveLength(2);
  });
});

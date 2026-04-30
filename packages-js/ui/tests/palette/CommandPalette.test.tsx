import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { CommandPalette, type PaletteCommand } from '../../src/palette/CommandPalette.js';

const RECENT_KEY = 'arqel:cmdpal:recent';

const sample: PaletteCommand[] = [
  { id: 'goto-users', label: 'Go to Users', url: '/admin/users', category: 'Navigation' },
  { id: 'create-post', label: 'Create Post', url: '/admin/posts/create', category: 'Create' },
];

function mockFetch(commands: PaletteCommand[]) {
  return vi.fn(
    async () =>
      new Response(JSON.stringify({ commands }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
  );
}

let assignSpy: ReturnType<typeof vi.fn>;

beforeEach(() => {
  vi.useFakeTimers();
  // jsdom lacks <dialog> support — patch in lightweight stubs.
  if (!HTMLDialogElement.prototype.showModal) {
    HTMLDialogElement.prototype.showModal = function () {
      this.setAttribute('open', '');
      (this as unknown as { open: boolean }).open = true;
    };
  }
  if (!HTMLDialogElement.prototype.close) {
    HTMLDialogElement.prototype.close = function () {
      this.removeAttribute('open');
      (this as unknown as { open: boolean }).open = false;
    };
  }
  localStorage.clear();
  assignSpy = vi.fn();
  Object.defineProperty(window, 'location', {
    configurable: true,
    value: { ...window.location, assign: assignSpy },
  });
});

afterEach(() => {
  vi.useRealTimers();
  vi.restoreAllMocks();
});

function pressCmdK() {
  fireEvent.keyDown(window, { key: 'k', metaKey: true });
}

async function flushMicrotasks() {
  await act(async () => {
    await Promise.resolve();
  });
}

describe('CommandPalette', () => {
  it('opens when Cmd+K is pressed', async () => {
    globalThis.fetch = mockFetch([]) as unknown as typeof fetch;
    render(<CommandPalette />);
    expect(document.querySelector('dialog')?.hasAttribute('open')).toBe(false);
    act(() => pressCmdK());
    await flushMicrotasks();
    expect(document.querySelector('dialog')?.hasAttribute('open')).toBe(true);
    expect(screen.getByRole('combobox')).toBeInTheDocument();
  });

  it('opens when Ctrl+K is pressed', async () => {
    globalThis.fetch = mockFetch([]) as unknown as typeof fetch;
    render(<CommandPalette />);
    act(() => fireEvent.keyDown(window, { key: 'K', ctrlKey: true }));
    await flushMicrotasks();
    expect(document.querySelector('dialog')?.hasAttribute('open')).toBe(true);
  });

  it('closes when Escape is pressed', async () => {
    globalThis.fetch = mockFetch([]) as unknown as typeof fetch;
    render(<CommandPalette />);
    act(() => pressCmdK());
    await flushMicrotasks();
    const input = screen.getByRole('combobox');
    act(() => fireEvent.keyDown(input, { key: 'Escape' }));
    expect(document.querySelector('dialog')?.hasAttribute('open')).toBe(false);
  });

  it('debounces fetch and renders results grouped by category', async () => {
    const fetchMock = mockFetch(sample);
    globalThis.fetch = fetchMock as unknown as typeof fetch;
    render(<CommandPalette />);
    act(() => pressCmdK());
    await flushMicrotasks();
    const input = screen.getByRole('combobox') as HTMLInputElement;
    act(() => fireEvent.change(input, { target: { value: 'us' } }));
    expect(fetchMock).not.toHaveBeenCalled();
    await act(async () => {
      vi.advanceTimersByTime(160);
    });
    await flushMicrotasks();
    await flushMicrotasks();
    expect(fetchMock).toHaveBeenCalledTimes(1);
    const firstCall = fetchMock.mock.calls[0] as unknown as [string];
    expect(firstCall[0]).toContain('/admin/commands?q=us');
    expect(screen.getByText('Go to Users')).toBeInTheDocument();
    expect(screen.getByText('Create Post')).toBeInTheDocument();
    expect(screen.getByText('Navigation')).toBeInTheDocument();
    expect(screen.getByText('Create')).toBeInTheDocument();
  });

  it('navigates with ArrowDown and executes with Enter', async () => {
    globalThis.fetch = mockFetch(sample) as unknown as typeof fetch;
    render(<CommandPalette />);
    act(() => pressCmdK());
    await flushMicrotasks();
    const input = screen.getByRole('combobox') as HTMLInputElement;
    act(() => fireEvent.change(input, { target: { value: 'x' } }));
    await act(async () => {
      vi.advanceTimersByTime(160);
    });
    await flushMicrotasks();
    await flushMicrotasks();
    // First option starts highlighted (index 0); ArrowDown moves to index 1.
    act(() => fireEvent.keyDown(input, { key: 'ArrowDown' }));
    const options = screen.getAllByRole('option');
    expect(options[1]?.getAttribute('aria-selected')).toBe('true');
    act(() => fireEvent.keyDown(input, { key: 'Enter' }));
    expect(assignSpy).toHaveBeenCalledWith('/admin/posts/create');
  });

  it('shows recent commands when query is empty', async () => {
    localStorage.setItem(
      RECENT_KEY,
      JSON.stringify([
        {
          id: 'goto-users',
          count: 3,
          lastUsed: Date.now(),
          command: sample[0],
        },
      ]),
    );
    globalThis.fetch = mockFetch([]) as unknown as typeof fetch;
    render(<CommandPalette />);
    act(() => pressCmdK());
    await flushMicrotasks();
    expect(screen.getByText('Recent')).toBeInTheDocument();
    expect(screen.getByText('Go to Users')).toBeInTheDocument();
  });

  it('records usage in localStorage after executing a command', async () => {
    globalThis.fetch = mockFetch(sample) as unknown as typeof fetch;
    render(<CommandPalette />);
    act(() => pressCmdK());
    await flushMicrotasks();
    const input = screen.getByRole('combobox') as HTMLInputElement;
    act(() => fireEvent.change(input, { target: { value: 'go' } }));
    await act(async () => {
      vi.advanceTimersByTime(160);
    });
    await flushMicrotasks();
    await flushMicrotasks();
    act(() => fireEvent.keyDown(input, { key: 'Enter' }));
    const stored = JSON.parse(localStorage.getItem(RECENT_KEY) ?? '[]') as Array<{
      id: string;
      count: number;
    }>;
    expect(stored.find((e) => e.id === 'goto-users')?.count).toBe(1);
    expect(assignSpy).toHaveBeenCalledWith('/admin/users');
  });

  it('exposes aria-live region with command count', async () => {
    globalThis.fetch = mockFetch(sample) as unknown as typeof fetch;
    render(<CommandPalette />);
    act(() => pressCmdK());
    await flushMicrotasks();
    const input = screen.getByRole('combobox') as HTMLInputElement;
    act(() => fireEvent.change(input, { target: { value: 'x' } }));
    await act(async () => {
      vi.advanceTimersByTime(160);
    });
    await flushMicrotasks();
    await flushMicrotasks();
    const status = screen.getByRole('status');
    expect(status.textContent).toMatch(/2 commands/);
  });
});

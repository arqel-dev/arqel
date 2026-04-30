import { act, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { reload } = vi.hoisted(() => ({ reload: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
  router: { reload },
}));

import { ConnectionStatusBanner } from './ConnectionStatusBanner';

type Handler = (data?: unknown) => void;

interface FakeConnection {
  bind: ReturnType<typeof vi.fn>;
  unbind: ReturnType<typeof vi.fn>;
  state: string;
  fire(event: string): void;
}

function makeFakeConnection(initialState = 'connecting'): FakeConnection {
  const handlers = new Map<string, Set<Handler>>();
  return {
    bind: vi.fn((event: string, cb: Handler) => {
      if (!handlers.has(event)) handlers.set(event, new Set());
      handlers.get(event)!.add(cb);
    }),
    unbind: vi.fn(),
    state: initialState,
    fire(event: string) {
      const set = handlers.get(event);
      if (!set) return;
      for (const cb of set) cb();
    },
  };
}

interface WindowWithEcho {
  Echo?: unknown;
}

function installEcho(connection: FakeConnection) {
  (window as unknown as WindowWithEcho).Echo = {
    connector: { pusher: { connection } },
  };
}

describe('ConnectionStatusBanner', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    reload.mockClear();
  });

  afterEach(() => {
    delete (window as unknown as WindowWithEcho).Echo;
    vi.useRealTimers();
  });

  it('renderiza null quando o status é connected', () => {
    const conn = makeFakeConnection('connecting');
    installEcho(conn);

    const { container } = render(<ConnectionStatusBanner />);
    act(() => conn.fire('connected'));

    expect(container.firstChild).toBeNull();
  });

  it('renderiza null quando window.Echo está ausente (degrada para unavailable)', () => {
    const { container } = render(<ConnectionStatusBanner />);
    act(() => {
      vi.advanceTimersByTime(6000);
    });
    expect(container.firstChild).toBeNull();
  });

  it('renderiza banner com role=status quando disconnected', () => {
    const conn = makeFakeConnection('connecting');
    installEcho(conn);

    render(<ConnectionStatusBanner />);
    act(() => conn.fire('connected'));
    act(() => conn.fire('disconnected'));

    const banner = screen.getByRole('status');
    expect(banner.getAttribute('aria-live')).toBe('polite');
    expect(banner.getAttribute('data-status')).toBe('disconnected');
    expect(banner.textContent).toContain('Connection lost');
  });

  it('chama renderBanner com status e retryCount quando fornecido', () => {
    const conn = makeFakeConnection('connecting');
    installEcho(conn);

    const renderBanner = vi.fn((status: string, retryCount: number) => (
      <span data-testid="custom">
        {status}:{retryCount}
      </span>
    ));

    render(<ConnectionStatusBanner renderBanner={renderBanner} />);
    act(() => conn.fire('connected'));
    act(() => conn.fire('disconnected'));

    expect(renderBanner).toHaveBeenCalled();
    const lastCall = renderBanner.mock.calls.at(-1);
    expect(lastCall?.[0]).toBe('disconnected');
    expect(lastCall?.[1]).toBe(1);
    expect(screen.getByTestId('custom').textContent).toBe('disconnected:1');
  });

  it('ativa polling apenas durante disconnect quando pollOnDisconnect=true', () => {
    const conn = makeFakeConnection('connecting');
    installEcho(conn);

    render(
      <ConnectionStatusBanner pollOnDisconnect pollIntervalMs={1000} pollOnly={['records']} />,
    );

    act(() => conn.fire('connected'));
    act(() => {
      vi.advanceTimersByTime(2000);
    });
    expect(reload).not.toHaveBeenCalled();

    act(() => conn.fire('disconnected'));
    act(() => {
      vi.advanceTimersByTime(1000);
    });
    expect(reload).toHaveBeenCalledTimes(1);
    expect(reload).toHaveBeenLastCalledWith({ only: ['records'] });

    act(() => conn.fire('connected'));
    reload.mockClear();
    act(() => {
      vi.advanceTimersByTime(5000);
    });
    expect(reload).not.toHaveBeenCalled();
  });
});

import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useConnectionStatus } from './useConnectionStatus';

type Handler = (data?: unknown) => void;

interface FakeConnection {
  bind: ReturnType<typeof vi.fn>;
  unbind: ReturnType<typeof vi.fn>;
  state: string;
  fire(event: string): void;
  handlers: Map<string, Set<Handler>>;
}

function makeFakeConnection(initialState = 'connecting'): FakeConnection {
  const handlers = new Map<string, Set<Handler>>();
  const bind = vi.fn((event: string, cb: Handler) => {
    if (!handlers.has(event)) handlers.set(event, new Set());
    handlers.get(event)!.add(cb);
  });
  const unbind = vi.fn((event: string, cb?: Handler) => {
    const set = handlers.get(event);
    if (!set) return;
    if (cb) set.delete(cb);
    else set.clear();
  });
  return {
    bind,
    unbind,
    state: initialState,
    handlers,
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

describe('useConnectionStatus', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    delete (window as unknown as WindowWithEcho).Echo;
    vi.useRealTimers();
  });

  it('degrada para unavailable após o timeout quando window.Echo não aparece', () => {
    const { result } = renderHook(() => useConnectionStatus());
    // Estado inicial: ainda esperando Echo aparecer.
    expect(result.current.status).toBe('connecting');

    act(() => {
      vi.advanceTimersByTime(6000);
    });

    expect(result.current.status).toBe('unavailable');
    expect(result.current.lastConnectedAt).toBeNull();
    expect(result.current.retryCount).toBe(0);
  });

  it('faz bind dos eventos do connection.pusher quando Echo está presente', () => {
    const conn = makeFakeConnection('connecting');
    installEcho(conn);

    renderHook(() => useConnectionStatus());

    const events = conn.bind.mock.calls.map((c) => c[0]);
    expect(events).toEqual(
      expect.arrayContaining(['connected', 'connecting', 'disconnected', 'failed', 'unavailable']),
    );
  });

  it('reflete o último evento Pusher recebido', () => {
    const conn = makeFakeConnection('connecting');
    installEcho(conn);

    const { result } = renderHook(() => useConnectionStatus());
    expect(result.current.status).toBe('connecting');

    act(() => conn.fire('connected'));
    expect(result.current.status).toBe('connected');
    expect(result.current.lastConnectedAt).not.toBeNull();

    act(() => conn.fire('disconnected'));
    expect(result.current.status).toBe('disconnected');

    act(() => conn.fire('failed'));
    expect(result.current.status).toBe('failed');
  });

  it('chama unbind para todos os eventos no cleanup', () => {
    const conn = makeFakeConnection('connecting');
    installEcho(conn);

    const { unmount } = renderHook(() => useConnectionStatus());
    expect(conn.bind).toHaveBeenCalled();

    unmount();

    const unbindEvents = conn.unbind.mock.calls.map((c) => c[0]);
    expect(unbindEvents).toEqual(
      expect.arrayContaining(['connected', 'connecting', 'disconnected', 'failed', 'unavailable']),
    );
  });

  it('incrementa retryCount em cada ciclo connected → disconnected', () => {
    const conn = makeFakeConnection('connecting');
    installEcho(conn);

    const { result } = renderHook(() => useConnectionStatus());

    act(() => conn.fire('connected'));
    expect(result.current.retryCount).toBe(0);

    act(() => conn.fire('disconnected'));
    expect(result.current.retryCount).toBe(1);

    act(() => conn.fire('connected'));
    act(() => conn.fire('disconnected'));
    expect(result.current.retryCount).toBe(2);
  });

  it('subscreve tarde quando window.Echo aparece após o mount', () => {
    const { result } = renderHook(() => useConnectionStatus());
    expect(result.current.status).toBe('connecting');

    const conn = makeFakeConnection('connected');
    installEcho(conn);

    // Avança o intervalo de polling (250ms).
    act(() => {
      vi.advanceTimersByTime(300);
    });

    expect(conn.bind).toHaveBeenCalled();
    expect(result.current.status).toBe('connected');
  });
});

import { router } from '@inertiajs/react';
import { renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  type EchoChannelLike,
  type EchoLike,
  useResourceUpdates,
} from '../src/useResourceUpdates.js';

interface ListenerEntry {
  event: string;
  callback: (payload: Record<string, unknown>) => void;
}

interface MockEcho extends EchoLike {
  privateMock: ReturnType<typeof vi.fn>;
  leaveMock: ReturnType<typeof vi.fn>;
  channels: Map<string, ListenerEntry[]>;
  emit: (channel: string, event: string, payload: Record<string, unknown>) => void;
}

function createMockEcho(): MockEcho {
  const channels = new Map<string, ListenerEntry[]>();
  const leaveMock = vi.fn((channel: string) => {
    channels.delete(channel);
  });
  const privateMock = vi.fn((channel: string): EchoChannelLike => {
    const listeners: ListenerEntry[] = [];
    channels.set(channel, listeners);
    const channelObj: EchoChannelLike = {
      listen(event, callback) {
        listeners.push({ event, callback: callback as ListenerEntry['callback'] });
        return channelObj;
      },
    };
    return channelObj;
  });

  return {
    private: privateMock,
    leave: leaveMock,
    privateMock,
    leaveMock,
    channels,
    emit(channel, event, payload) {
      const list = channels.get(channel);
      if (!list) return;
      for (const entry of list) {
        if (entry.event === event) entry.callback(payload);
      }
    },
  };
}

const reloadMock = router.reload as unknown as ReturnType<typeof vi.fn>;

beforeEach(() => {
  reloadMock.mockClear();
});

afterEach(() => {
  // biome-ignore lint/performance/noDelete: cleanly remove ambient global between tests.
  delete (window as { Echo?: EchoLike }).Echo;
});

describe('useResourceUpdates', () => {
  it('is a no-op when window.Echo is undefined', () => {
    expect(window.Echo).toBeUndefined();
    expect(() => {
      renderHook(() => useResourceUpdates('posts'));
    }).not.toThrow();
    expect(reloadMock).not.toHaveBeenCalled();
  });

  it('subscribes to the resource-level channel when no recordId is provided', () => {
    const echo = createMockEcho();
    window.Echo = echo;

    renderHook(() => useResourceUpdates('posts'));

    expect(echo.privateMock).toHaveBeenCalledTimes(1);
    expect(echo.privateMock).toHaveBeenCalledWith('arqel.posts');
  });

  it('subscribes to the record-level channel when recordId is provided', () => {
    const echo = createMockEcho();
    window.Echo = echo;

    renderHook(() => useResourceUpdates('posts', 42));

    expect(echo.privateMock).toHaveBeenCalledWith('arqel.posts.42');
  });

  it('triggers a partial reload of `records` on update without recordId', () => {
    const echo = createMockEcho();
    window.Echo = echo;

    renderHook(() => useResourceUpdates('posts'));
    echo.emit('arqel.posts', '.ResourceUpdated', { updatedByUserId: 7 });

    expect(reloadMock).toHaveBeenCalledTimes(1);
    expect(reloadMock).toHaveBeenCalledWith({ only: ['records'] });
  });

  it('triggers a partial reload of `record` on update with recordId', () => {
    const echo = createMockEcho();
    window.Echo = echo;

    renderHook(() => useResourceUpdates('posts', 42));
    echo.emit('arqel.posts.42', '.ResourceUpdated', {});

    expect(reloadMock).toHaveBeenCalledWith({ only: ['record'] });
  });

  it('invokes the onUpdate callback with the broadcast payload', () => {
    const echo = createMockEcho();
    window.Echo = echo;

    const onUpdate = vi.fn();
    renderHook(() => useResourceUpdates('posts', 42, { onUpdate }));

    const payload = { updatedByUserId: 7, updatedByName: 'Ada' };
    echo.emit('arqel.posts.42', '.ResourceUpdated', payload);

    expect(onUpdate).toHaveBeenCalledTimes(1);
    expect(onUpdate).toHaveBeenCalledWith(payload);
  });

  it('leaves the channel on unmount', () => {
    const echo = createMockEcho();
    window.Echo = echo;

    const { unmount } = renderHook(() => useResourceUpdates('posts', 42));
    expect(echo.leaveMock).not.toHaveBeenCalled();

    unmount();
    expect(echo.leaveMock).toHaveBeenCalledWith('arqel.posts.42');
  });

  it('respects a custom event name', () => {
    const echo = createMockEcho();
    window.Echo = echo;

    const onUpdate = vi.fn();
    renderHook(() => useResourceUpdates('posts', undefined, { event: '.PostSaved', onUpdate }));

    echo.emit('arqel.posts', '.ResourceUpdated', { ignored: true });
    expect(onUpdate).not.toHaveBeenCalled();

    echo.emit('arqel.posts', '.PostSaved', { ok: true });
    expect(onUpdate).toHaveBeenCalledWith({ ok: true });
  });
});

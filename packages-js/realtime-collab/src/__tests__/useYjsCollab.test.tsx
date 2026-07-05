import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as Y from 'yjs';
import { encodeUpdate } from '../encoders';
import { useYjsCollab } from '../useYjsCollab';

interface MockChannel {
  listen: ReturnType<typeof vi.fn>;
  stopListening: ReturnType<typeof vi.fn>;
  emit(event: string, payload: { state?: string }): void;
}

function makeMockEcho(): { echo: { private: ReturnType<typeof vi.fn> }; channel: MockChannel } {
  let stored: ((payload: { state?: string }) => void) | null = null;
  const channel: MockChannel = {
    listen: vi.fn((_event: string, cb: (payload: { state?: string }) => void) => {
      stored = cb;
      return channel;
    }),
    stopListening: vi.fn(() => channel),
    emit: (_event, payload) => stored?.(payload),
  };
  const echo = { private: vi.fn(() => channel) };
  return { echo, channel };
}

describe('useYjsCollab', () => {
  beforeEach(() => {
    delete (window as unknown as { Echo?: unknown }).Echo;
    vi.stubGlobal(
      'fetch',
      vi.fn(() => Promise.resolve(new Response(null, { status: 204 }))),
    );
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('creates a Y.Doc and Y.Text on mount', () => {
    const { result } = renderHook(() =>
      useYjsCollab({ modelType: 'posts', modelId: 1, field: 'body' }),
    );
    expect(result.current.doc).toBeInstanceOf(Y.Doc);
    expect(result.current.text).toBeInstanceOf(Y.Text);
  });

  it('sets status to offline when window.Echo is missing', () => {
    const { result } = renderHook(() =>
      useYjsCollab({ modelType: 'posts', modelId: 1, field: 'body' }),
    );
    expect(result.current.status).toBe('offline');
  });

  it('subscribes to Echo private channel and transitions to synced', () => {
    const { echo, channel } = makeMockEcho();
    (window as unknown as { Echo: unknown }).Echo = echo;

    const { result } = renderHook(() =>
      useYjsCollab({ modelType: 'posts', modelId: 42, field: 'body' }),
    );

    expect(echo.private).toHaveBeenCalledWith('arqel.collab.posts.42.body');
    expect(channel.listen).toHaveBeenCalled();
    expect(result.current.status).toBe('synced');
  });

  it('cleans up the listener on unmount', () => {
    const { echo, channel } = makeMockEcho();
    (window as unknown as { Echo: unknown }).Echo = echo;

    const { unmount } = renderHook(() =>
      useYjsCollab({ modelType: 'posts', modelId: 1, field: 'body' }),
    );

    unmount();

    expect(channel.stopListening).toHaveBeenCalled();
  });

  it('applyRemote applies a Yjs update onto the local doc', () => {
    const { result } = renderHook(() =>
      useYjsCollab({ modelType: 'posts', modelId: 1, field: 'body' }),
    );

    // Build an update from a sibling Y.Doc
    const remote = new Y.Doc();
    remote.getText('content').insert(0, 'hello');
    const update = Y.encodeStateAsUpdate(remote);

    act(() => {
      result.current.applyRemote(update);
    });

    expect(result.current.text.toString()).toBe('hello');
  });

  it('applyRemote accepts base64-encoded updates', () => {
    const { result } = renderHook(() =>
      useYjsCollab({ modelType: 'posts', modelId: 1, field: 'body' }),
    );

    const remote = new Y.Doc();
    remote.getText('content').insert(0, 'world');
    const update = Y.encodeStateAsUpdate(remote);
    const b64 = encodeUpdate(update);

    act(() => {
      result.current.applyRemote(b64);
    });

    expect(result.current.text.toString()).toBe('world');
  });

  // `applyRemote` is part of the public hook result, so a consumer may feed it
  // an arbitrary/untrusted update (e.g. a snapshot fetched from an outdated
  // endpoint). It must never throw on malformed input — it swallows the bad
  // update and leaves the local doc untouched, exactly as the internal
  // snapshot/broadcast call-sites already expect.
  it('applyRemote does not throw on a malformed base64 string', () => {
    const { result } = renderHook(() =>
      useYjsCollab({ modelType: 'posts', modelId: 1, field: 'body' }),
    );
    result.current.text.insert(0, 'keep');

    expect(() => {
      act(() => {
        result.current.applyRemote('not valid base64 !!!');
      });
    }).not.toThrow();
    // The bad update is ignored; existing content survives.
    expect(result.current.text.toString()).toBe('keep');
  });

  it('applyRemote does not throw on base64 that decodes to non-Yjs bytes', () => {
    const { result } = renderHook(() =>
      useYjsCollab({ modelType: 'posts', modelId: 1, field: 'body' }),
    );

    // Valid base64 charset, but the decoded bytes are not a Yjs update.
    const garbage = encodeUpdate(new Uint8Array([255, 254, 253, 252, 251]));

    expect(() => {
      act(() => {
        result.current.applyRemote(garbage);
      });
    }).not.toThrow();
  });
});

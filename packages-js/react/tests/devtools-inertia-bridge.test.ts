import { describe, expect, it } from 'vitest';

import { createDevToolsHook } from '../src/devtools/devtools.js';
import {
  type InertiaRouterEvent,
  type InertiaRouterLike,
  installInertiaBridge,
} from '../src/devtools/inertia-bridge.js';

interface FakeRouter extends InertiaRouterLike {
  emit(event: 'start' | 'navigate' | 'finish', payload: InertiaRouterEvent): void;
  listenerCount(event: string): number;
}

function createFakeRouter(): FakeRouter {
  const listeners = new Map<string, Set<(ev: InertiaRouterEvent) => void>>();
  return {
    on(event, cb) {
      if (!listeners.has(event)) {
        listeners.set(event, new Set());
      }
      listeners.get(event)?.add(cb);
      return () => {
        listeners.get(event)?.delete(cb);
      };
    },
    emit(event, payload) {
      for (const cb of listeners.get(event) ?? []) {
        cb(payload);
      }
    },
    listenerCount(event) {
      return listeners.get(event)?.size ?? 0;
    },
  };
}

describe('installInertiaBridge', () => {
  it('forwards navigate events into hook.setPageProps', () => {
    const hook = createDevToolsHook('test');
    const router = createFakeRouter();
    installInertiaBridge(hook, router, { now: () => 1000 });

    router.emit('navigate', {
      detail: {
        page: {
          url: '/admin/users',
          props: { users: [1, 2], auth: { id: 5 } },
        },
      },
    });

    const state = hook.getState();
    expect(state.currentPath).toBe('/admin/users');
    expect(state.pageProps).toEqual({ users: [1, 2], auth: { id: 5 } });
    expect(state.sharedProps).toEqual({ auth: { id: 5 } });
  });

  it('records navigation entry with duration when start fires before navigate', () => {
    const hook = createDevToolsHook('test');
    const router = createFakeRouter();
    const times = [100, 250]; // start, navigate
    let i = 0;
    installInertiaBridge(hook, router, { now: () => times[i++] ?? 0 });

    router.emit('start', {});
    router.emit('navigate', { detail: { page: { url: '/x', props: {} } } });

    const history = hook.getState().navigationHistory;
    expect(history).toHaveLength(1);
    expect(history[0]).toEqual({ path: '/x', timestamp: 250, durationMs: 150 });
  });

  it('records navigation without duration when start did not fire', () => {
    const hook = createDevToolsHook('test');
    const router = createFakeRouter();
    installInertiaBridge(hook, router, { now: () => 999 });

    router.emit('navigate', { detail: { page: { url: '/y', props: {} } } });

    const entry = hook.getState().navigationHistory[0];
    expect(entry).toBeDefined();
    expect(entry?.path).toBe('/y');
    expect(entry?.timestamp).toBe(999);
    expect(entry?.durationMs).toBeUndefined();
  });

  it('teardown removes both router listeners', () => {
    const hook = createDevToolsHook('test');
    const router = createFakeRouter();
    const teardown = installInertiaBridge(hook, router);
    expect(router.listenerCount('start')).toBe(1);
    expect(router.listenerCount('navigate')).toBe(1);

    teardown();

    expect(router.listenerCount('start')).toBe(0);
    expect(router.listenerCount('navigate')).toBe(0);
  });

  it('only forwards canonical shared keys (auth/flash/errors/csrf_token/arqel)', () => {
    const hook = createDevToolsHook('test');
    const router = createFakeRouter();
    installInertiaBridge(hook, router);

    router.emit('navigate', {
      detail: {
        page: {
          url: '/z',
          props: {
            auth: { id: 1 },
            flash: { ok: true },
            errors: {},
            csrf_token: 'abc',
            arqel: { panel: 'admin' },
            users: [],
            extra: 'should-not-be-shared',
          },
        },
      },
    });

    const shared = hook.getState().sharedProps;
    expect(Object.keys(shared).sort()).toEqual(
      ['arqel', 'auth', 'csrf_token', 'errors', 'flash'].sort(),
    );
    expect((shared as Record<string, unknown>)['extra']).toBeUndefined();
    // pageProps still has everything.
    expect(hook.getState().pageProps).toMatchObject({ users: [], extra: 'should-not-be-shared' });
  });

  it('handles navigate event with missing detail gracefully', () => {
    const hook = createDevToolsHook('test');
    const router = createFakeRouter();
    installInertiaBridge(hook, router, { now: () => 1 });

    expect(() => router.emit('navigate', {})).not.toThrow();
    const state = hook.getState();
    expect(state.currentPath).toBe('');
    expect(state.pageProps).toEqual({});
    expect(state.sharedProps).toEqual({});
  });
});

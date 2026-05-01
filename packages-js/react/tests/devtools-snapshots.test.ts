import { describe, expect, it } from 'vitest';

import {
  createDevToolsHook,
  type NavigationSnapshot,
  SNAPSHOT_HISTORY_LIMIT,
} from '../src/devtools/devtools.js';
import {
  type InertiaRouterEvent,
  type InertiaRouterLike,
  installInertiaBridge,
} from '../src/devtools/inertia-bridge.js';

function snapshot(id: string, timestamp: number, url = '/'): NavigationSnapshot {
  return {
    id,
    timestamp,
    url,
    pageProps: { foo: id },
    sharedProps: {},
  };
}

function fakeRouter() {
  const listeners = new Map<string, Set<(ev: InertiaRouterEvent) => void>>();
  const router: InertiaRouterLike & {
    emit: (event: 'start' | 'navigate' | 'finish', payload: InertiaRouterEvent) => void;
  } = {
    on(event, cb) {
      const set = listeners.get(event) ?? new Set();
      set.add(cb);
      listeners.set(event, set);
      return () => listeners.get(event)?.delete(cb);
    },
    emit(event, payload) {
      for (const cb of listeners.get(event) ?? []) cb(payload);
    },
  };
  return router;
}

describe('createDevToolsHook snapshots (DEVTOOLS-006)', () => {
  it('pushSnapshot adds entries to the buffer', () => {
    const hook = createDevToolsHook('test');
    hook.pushSnapshot(snapshot('a', 1, '/a'));
    hook.pushSnapshot(snapshot('b', 2, '/b'));
    expect(hook.getSnapshots()).toHaveLength(2);
  });

  it('respects the SNAPSHOT_HISTORY_LIMIT ring buffer cap', () => {
    const hook = createDevToolsHook('test');
    for (let i = 0; i < SNAPSHOT_HISTORY_LIMIT + 5; i++) {
      hook.pushSnapshot(snapshot(`s${i}`, i, `/p${i}`));
    }
    const snaps = hook.getSnapshots();
    expect(snaps).toHaveLength(SNAPSHOT_HISTORY_LIMIT);
    // Reverse-chronological — first item is the latest pushed.
    expect(snaps[0]?.id).toBe(`s${SNAPSHOT_HISTORY_LIMIT + 4}`);
  });

  it('getSnapshots returns most-recent-first ordering', () => {
    const hook = createDevToolsHook('test');
    hook.pushSnapshot(snapshot('first', 100));
    hook.pushSnapshot(snapshot('second', 200));
    hook.pushSnapshot(snapshot('third', 300));
    const snaps = hook.getSnapshots();
    expect(snaps.map((s) => s.id)).toEqual(['third', 'second', 'first']);
  });

  it('produces unique snapshot IDs across rapid navigations', () => {
    const hook = createDevToolsHook('test');
    const router = fakeRouter();
    let i = 0;
    const times = [10, 20, 30, 40];
    installInertiaBridge(hook, router, { now: () => times[i++] ?? 50 });

    router.emit('navigate', { detail: { page: { url: '/a', props: {} } } });
    router.emit('navigate', { detail: { page: { url: '/b', props: {} } } });
    router.emit('navigate', { detail: { page: { url: '/c', props: {} } } });

    const snaps = hook.getSnapshots();
    const ids = new Set(snaps.map((s) => s.id));
    expect(ids.size).toBe(snaps.length);
    expect(snaps).toHaveLength(3);
  });

  it('inertia bridge captures full pageProps + sharedProps in the snapshot', () => {
    const hook = createDevToolsHook('test');
    const router = fakeRouter();
    installInertiaBridge(hook, router, { now: () => 5_000 });

    router.emit('navigate', {
      detail: {
        page: {
          url: '/admin/users',
          props: { users: [1, 2, 3], auth: { id: 7 }, extra: 'z' },
        },
      },
    });

    const snaps = hook.getSnapshots();
    expect(snaps).toHaveLength(1);
    const [snap] = snaps;
    expect(snap?.url).toBe('/admin/users');
    expect(snap?.pageProps).toEqual({ users: [1, 2, 3], auth: { id: 7 }, extra: 'z' });
    expect(snap?.sharedProps).toEqual({ auth: { id: 7 } });
    expect(snap?.timestamp).toBe(5_000);
  });
});

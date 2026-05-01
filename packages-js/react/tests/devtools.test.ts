import { afterEach, describe, expect, it, vi } from 'vitest';

import {
  type ArqelDevToolsState,
  createDevToolsHook,
  installDevToolsHook,
  NAVIGATION_HISTORY_LIMIT,
} from '../src/devtools/devtools.js';

afterEach(() => {
  delete (window as Window).__ARQEL_DEVTOOLS_HOOK__;
  vi.unstubAllEnvs();
});

describe('installDevToolsHook (DEV mode)', () => {
  it('installs the hook on window when DEV is true', () => {
    vi.stubEnv('DEV', true);
    const hook = installDevToolsHook('1.2.3');
    expect(hook).toBeDefined();
    expect(window.__ARQEL_DEVTOOLS_HOOK__).toBe(hook);
    expect(window.__ARQEL_DEVTOOLS_HOOK__?.version).toBe('1.2.3');
  });

  it('exposes a getState() returning the empty initial snapshot', () => {
    vi.stubEnv('DEV', true);
    const hook = installDevToolsHook('1.0.0');
    expect(hook?.getState()).toEqual({
      panel: null,
      resource: null,
      sharedProps: {},
      pageProps: null,
      currentPath: '',
      navigationHistory: [],
      devToolsPayload: null,
      fieldsSchema: [],
      snapshots: [],
      performanceMetrics: {
        lcp: null,
        inp: null,
        fid: null,
        cls: null,
        navigationTime: null,
      },
    });
  });

  it('does not double-install when called twice (idempotent during HMR)', () => {
    vi.stubEnv('DEV', true);
    const first = installDevToolsHook('1.0.0');
    const second = installDevToolsHook('9.9.9');
    expect(second).toBe(first);
    // First version wins — no clobbering of an already-installed runtime.
    expect(window.__ARQEL_DEVTOOLS_HOOK__?.version).toBe('1.0.0');
  });
});

describe('installDevToolsHook (production mode)', () => {
  it('is a no-op when NODE_ENV is production', () => {
    vi.stubEnv('NODE_ENV', 'production');
    const hook = installDevToolsHook('1.0.0');
    expect(hook).toBeUndefined();
    expect(window.__ARQEL_DEVTOOLS_HOOK__).toBeUndefined();
  });
});

describe('createDevToolsHook subscribe/notify', () => {
  it('invokes subscribers when state changes and stops after unsubscribe', () => {
    const hook = createDevToolsHook('test');
    const calls: ArqelDevToolsState[] = [];
    const unsubscribe = hook.subscribe((state) => {
      calls.push(state);
    });

    const next: ArqelDevToolsState = {
      panel: 'admin',
      resource: 'users',
      sharedProps: { auth: { id: 1 } },
      pageProps: { foo: 1 },
      currentPath: '/admin/users',
      navigationHistory: [],
      devToolsPayload: null,
      fieldsSchema: [],
      snapshots: [],
      performanceMetrics: {
        lcp: null,
        inp: null,
        fid: null,
        cls: null,
        navigationTime: null,
      },
    };
    hook.__setState(next);
    expect(calls).toHaveLength(1);
    expect(calls[0]).toEqual(next);
    expect(hook.getState()).toEqual(next);

    unsubscribe();
    hook.__setState({
      panel: null,
      resource: null,
      sharedProps: {},
      pageProps: null,
      currentPath: '',
      navigationHistory: [],
      devToolsPayload: null,
      fieldsSchema: [],
      snapshots: [],
      performanceMetrics: {
        lcp: null,
        inp: null,
        fid: null,
        cls: null,
        navigationTime: null,
      },
    });
    expect(calls).toHaveLength(1);
  });

  it('supports multiple independent subscribers', () => {
    const hook = createDevToolsHook('test');
    const a = vi.fn();
    const b = vi.fn();
    hook.subscribe(a);
    hook.subscribe(b);
    hook.__setState({
      panel: 'p',
      resource: null,
      sharedProps: {},
      pageProps: null,
      currentPath: '',
      navigationHistory: [],
      devToolsPayload: null,
      fieldsSchema: [],
      snapshots: [],
      performanceMetrics: {
        lcp: null,
        inp: null,
        fid: null,
        cls: null,
        navigationTime: null,
      },
    });
    expect(a).toHaveBeenCalledTimes(1);
    expect(b).toHaveBeenCalledTimes(1);
  });
});

describe('createDevToolsHook setPageProps / recordNavigation (DEVTOOLS-003)', () => {
  it('setPageProps merges pageProps/sharedProps/currentPath and notifies subscribers', () => {
    const hook = createDevToolsHook('test');
    const calls: ArqelDevToolsState[] = [];
    hook.subscribe((s) => calls.push(s));

    hook.setPageProps({ users: [1, 2] }, { auth: { id: 7 } }, '/admin/users');

    expect(calls).toHaveLength(1);
    const state = hook.getState();
    expect(state.pageProps).toEqual({ users: [1, 2] });
    expect(state.sharedProps).toEqual({ auth: { id: 7 } });
    expect(state.currentPath).toBe('/admin/users');
    // panel/resource preserved from prior state
    expect(state.panel).toBeNull();
    expect(state.navigationHistory).toEqual([]);
  });

  it('recordNavigation appends entries with timestamp + duration', () => {
    const hook = createDevToolsHook('test');
    hook.recordNavigation({ path: '/a', timestamp: 100 });
    hook.recordNavigation({ path: '/b', timestamp: 200, durationMs: 42 });

    const history = hook.getState().navigationHistory;
    expect(history).toHaveLength(2);
    expect(history[0]).toEqual({ path: '/a', timestamp: 100 });
    expect(history[1]).toEqual({ path: '/b', timestamp: 200, durationMs: 42 });
  });

  it('navigationHistory ring buffer caps at NAVIGATION_HISTORY_LIMIT', () => {
    const hook = createDevToolsHook('test');
    for (let i = 0; i < NAVIGATION_HISTORY_LIMIT + 5; i++) {
      hook.recordNavigation({ path: `/p${i}`, timestamp: i });
    }
    const history = hook.getState().navigationHistory;
    expect(history).toHaveLength(NAVIGATION_HISTORY_LIMIT);
    // Oldest entries dropped — first should be /p5.
    expect(history[0]?.path).toBe('/p5');
    expect(history[history.length - 1]?.path).toBe(`/p${NAVIGATION_HISTORY_LIMIT + 4}`);
  });
});

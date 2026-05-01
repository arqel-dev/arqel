import { afterEach, describe, expect, it, vi } from 'vitest';

import {
  type ArqelDevToolsState,
  createDevToolsHook,
  installDevToolsHook,
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
    };
    hook.__setState(next);
    expect(calls).toHaveLength(1);
    expect(calls[0]).toEqual(next);
    expect(hook.getState()).toEqual(next);

    unsubscribe();
    hook.__setState({ panel: null, resource: null, sharedProps: {} });
    expect(calls).toHaveLength(1);
  });

  it('supports multiple independent subscribers', () => {
    const hook = createDevToolsHook('test');
    const a = vi.fn();
    const b = vi.fn();
    hook.subscribe(a);
    hook.subscribe(b);
    hook.__setState({ panel: 'p', resource: null, sharedProps: {} });
    expect(a).toHaveBeenCalledTimes(1);
    expect(b).toHaveBeenCalledTimes(1);
  });
});

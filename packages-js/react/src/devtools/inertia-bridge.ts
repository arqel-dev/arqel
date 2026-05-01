/**
 * Arqel DevTools — Inertia bridge (DEVTOOLS-003).
 *
 * Wires the Inertia router to the DevTools hook so the browser
 * extension can inspect the live `pageProps`, `sharedProps`, and a
 * navigation history ring buffer.
 *
 * Like the hook itself, this file MUST be a no-op in production: callers
 * should only invoke `installInertiaBridge` after `installDevToolsHook`
 * succeeded (i.e. only in dev builds — Vite then drops the whole branch).
 */

import type { ArqelDevToolsHook, ArqelDevToolsPayload, NavigationEntry } from './devtools.js';

/**
 * Minimal subset of the Inertia router surface that we need. Kept
 * structural so we don't take a hard `@inertiajs/core` dependency at
 * the type level (the bridge is optional and the runtime peer dep is
 * already declared by `@arqel/react`).
 */
export interface InertiaRouterLike {
  on(event: 'navigate' | 'finish' | 'start', cb: (event: InertiaRouterEvent) => void): () => void;
}

export interface InertiaRouterEvent {
  detail?: {
    page?: {
      url?: string;
      props?: Record<string, unknown>;
    };
    visit?: {
      url?: string | URL;
    };
  };
}

export interface InstallInertiaBridgeOptions {
  /**
   * Override `Date.now` (and `performance.now` if you have it). Lets
   * tests assert deterministic timestamps and durations.
   */
  now?: () => number;
}

/**
 * Subscribe to the Inertia router and forward each navigation to the
 * Arqel DevTools hook.
 *
 * Returns a teardown closure that removes both listeners.
 */
export function installInertiaBridge(
  hook: ArqelDevToolsHook,
  router: InertiaRouterLike,
  options: InstallInertiaBridgeOptions = {},
): () => void {
  const now = options.now ?? (() => Date.now());
  let visitStartedAt: number | null = null;

  const offStart = router.on('start', () => {
    visitStartedAt = now();
  });

  const offNavigate = router.on('navigate', (event: InertiaRouterEvent) => {
    const page = event.detail?.page ?? {};
    const props = (page.props ?? {}) as Record<string, unknown>;
    const url = typeof page.url === 'string' ? page.url : '';

    // Inertia conflates `pageProps` with `sharedProps` on `props`. We
    // cannot perfectly disambiguate without runtime help, so we treat
    // the full bag as `pageProps` and surface Arqel-conventional shared
    // keys (`auth`, `flash`, `errors`, `csrf_token`, `arqel`) as
    // `sharedProps` for the inspector.
    const sharedProps = pickSharedProps(props);

    hook.setPageProps(props, sharedProps, url);
    hook.setDevToolsPayload(extractDevToolsPayload(props));

    const timestamp = now();
    const entry: NavigationEntry =
      visitStartedAt !== null
        ? { path: url, timestamp, durationMs: timestamp - visitStartedAt }
        : { path: url, timestamp };
    hook.recordNavigation(entry);
    visitStartedAt = null;
  });

  return () => {
    offStart();
    offNavigate();
  };
}

const SHARED_PROP_KEYS = ['auth', 'flash', 'errors', 'csrf_token', 'arqel'] as const;

function pickSharedProps(props: Record<string, unknown>): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const key of SHARED_PROP_KEYS) {
    if (key in props) {
      out[key] = props[key];
    }
  }
  return out;
}

/**
 * Extract the convention-reserved `__devtools` shared prop emitted by
 * `arqel/core` in `local` environment (DEVTOOLS-004). Returns `null`
 * when the key is missing or shaped unexpectedly — production builds
 * never populate it.
 */
function extractDevToolsPayload(props: Record<string, unknown>): ArqelDevToolsPayload | null {
  const candidate = props['__devtools'];
  if (candidate === null || candidate === undefined) {
    return null;
  }
  if (typeof candidate !== 'object') {
    return null;
  }
  const bag = candidate as Record<string, unknown>;
  const policyLog = Array.isArray(bag['policyLog'])
    ? (bag['policyLog'] as ReadonlyArray<unknown>)
    : [];
  const queryCount = typeof bag['queryCount'] === 'number' ? bag['queryCount'] : 0;
  const memoryUsage = typeof bag['memoryUsage'] === 'number' ? bag['memoryUsage'] : 0;

  return {
    policyLog: policyLog as ArqelDevToolsPayload['policyLog'],
    queryCount,
    memoryUsage,
  };
}

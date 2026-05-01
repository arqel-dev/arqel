/**
 * Arqel DevTools hook (DEVTOOLS-002 + DEVTOOLS-003).
 *
 * Exposes a `window.__ARQEL_DEVTOOLS_HOOK__` marker that the browser
 * extension content script probes to detect Arqel-powered pages.
 *
 * IMPORTANT: this hook MUST NEVER be installed in production builds.
 * `installDevToolsHook` returns early unless `import.meta.env.DEV` is
 * truthy. Vite strips the dead branch at build time, so production
 * bundles ship without the hook surface at all.
 */

/** Maximum number of navigation entries kept in the ring buffer. */
export const NAVIGATION_HISTORY_LIMIT = 20;

export interface NavigationEntry {
  readonly path: string;
  readonly timestamp: number;
  readonly durationMs?: number;
}

/**
 * Convention-reserved `__devtools` shared prop emitted by
 * `arqel/core` in `local` environment (DEVTOOLS-004). Always `null`
 * in production builds — the PHP middleware refuses to populate it.
 */
export interface ArqelDevToolsPayload {
  readonly policyLog: ReadonlyArray<PolicyLogEntry>;
  readonly queryCount: number;
  readonly memoryUsage: number;
}

export interface PolicyLogEntry {
  readonly ability: string;
  readonly arguments: ReadonlyArray<unknown>;
  readonly result: boolean;
  readonly backtrace: ReadonlyArray<{
    readonly file: string | null;
    readonly line: number | null;
    readonly class: string | null;
    readonly function: string | null;
  }>;
  readonly timestamp: number;
}

export interface ArqelDevToolsState {
  readonly panel: string | null;
  readonly resource: string | null;
  readonly sharedProps: Readonly<Record<string, unknown>>;
  readonly pageProps: unknown;
  readonly currentPath: string;
  readonly navigationHistory: ReadonlyArray<NavigationEntry>;
  readonly devToolsPayload: ArqelDevToolsPayload | null;
  readonly fieldsSchema: ReadonlyArray<FieldSchema>;
}

/**
 * Normalized field schema entry surfaced by `getFieldsSchema()`
 * (DEVTOOLS-005). Heuristics extract the raw shape from `pageProps`
 * (`fields` / `resource.fields` / `form.fields`); missing keys fall
 * back to safe defaults (`visible: true`, `required: false`).
 */
export interface FieldSchema {
  readonly name: string;
  readonly type: string;
  readonly label?: string;
  readonly required?: boolean;
  readonly visible?: boolean;
  readonly rules?: ReadonlyArray<string>;
  readonly meta?: Readonly<Record<string, unknown>>;
}

export interface ArqelDevToolsHook {
  readonly version: string;
  getState(): ArqelDevToolsState;
  subscribe(callback: (state: ArqelDevToolsState) => void): () => void;
  /** Replace pageProps/sharedProps/currentPath in one shot (DEVTOOLS-003). */
  setPageProps(pageProps: unknown, sharedProps: Record<string, unknown>, currentPath: string): void;
  /** Append a navigation entry to the ring buffer (DEVTOOLS-003). */
  recordNavigation(entry: NavigationEntry): void;
  /** Returns the latest `__devtools` payload (DEVTOOLS-004) or null. */
  getDevToolsPayload(): ArqelDevToolsPayload | null;
  /** Replace the `__devtools` payload (DEVTOOLS-004). */
  setDevToolsPayload(payload: ArqelDevToolsPayload | null): void;
  /**
   * Extracts a normalized field schema list from the current
   * `pageProps` (DEVTOOLS-005). Tries `pageProps.fields`,
   * `pageProps.resource.fields`, then `pageProps.form.fields`.
   * Returns `[]` if nothing matches.
   */
  getFieldsSchema(): ReadonlyArray<FieldSchema>;
}

declare global {
  interface Window {
    __ARQEL_DEVTOOLS_HOOK__?: ArqelDevToolsHook;
  }
}

interface InternalHook extends ArqelDevToolsHook {
  /** Internal: replace state and notify subscribers (used by tests). */
  __setState(next: ArqelDevToolsState): void;
}

const EMPTY_STATE: ArqelDevToolsState = Object.freeze({
  panel: null,
  resource: null,
  sharedProps: Object.freeze({}),
  pageProps: null,
  currentPath: '',
  navigationHistory: Object.freeze([]),
  devToolsPayload: null,
  fieldsSchema: Object.freeze([]),
});

function isPlainObject(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * Heuristic extraction of a field schema list from arbitrary
 * `pageProps`. Tries the conventional Arqel paths and normalizes each
 * entry to {@link FieldSchema}. Non-array sources or non-object items
 * are skipped silently.
 */
export function extractFieldsSchema(pageProps: unknown): ReadonlyArray<FieldSchema> {
  if (!isPlainObject(pageProps)) return [];
  const candidates: unknown[] = [
    pageProps['fields'],
    isPlainObject(pageProps['resource']) ? pageProps['resource']['fields'] : undefined,
    isPlainObject(pageProps['form']) ? pageProps['form']['fields'] : undefined,
  ];
  for (const candidate of candidates) {
    if (Array.isArray(candidate)) {
      return Object.freeze(
        candidate
          .filter((item): item is Record<string, unknown> => isPlainObject(item))
          .map(normalizeField),
      );
    }
  }
  return [];
}

function normalizeField(raw: Record<string, unknown>): FieldSchema {
  const name = typeof raw['name'] === 'string' ? raw['name'] : '';
  const type = typeof raw['type'] === 'string' ? raw['type'] : 'unknown';
  const result: {
    name: string;
    type: string;
    label?: string;
    required: boolean;
    visible: boolean;
    rules?: ReadonlyArray<string>;
    meta?: Readonly<Record<string, unknown>>;
  } = {
    name,
    type,
    required: typeof raw['required'] === 'boolean' ? raw['required'] : false,
    visible: typeof raw['visible'] === 'boolean' ? raw['visible'] : true,
  };
  if (typeof raw['label'] === 'string') {
    result.label = raw['label'];
  }
  if (Array.isArray(raw['rules'])) {
    result.rules = Object.freeze(
      (raw['rules'] as unknown[]).filter((r): r is string => typeof r === 'string'),
    );
  }
  if (isPlainObject(raw['meta'])) {
    result.meta = Object.freeze({ ...raw['meta'] });
  }
  return Object.freeze(result);
}

/**
 * Internal factory — exported only for tests. Apps should call
 * `installDevToolsHook` instead.
 */
export function createDevToolsHook(version: string): InternalHook {
  let state: ArqelDevToolsState = EMPTY_STATE;
  const listeners = new Set<(state: ArqelDevToolsState) => void>();

  function notify(): void {
    for (const listener of listeners) {
      listener(state);
    }
  }

  return {
    version,
    getState(): ArqelDevToolsState {
      return state;
    },
    subscribe(callback: (state: ArqelDevToolsState) => void): () => void {
      listeners.add(callback);
      return () => {
        listeners.delete(callback);
      };
    },
    setPageProps(
      pageProps: unknown,
      sharedProps: Record<string, unknown>,
      currentPath: string,
    ): void {
      state = {
        ...state,
        pageProps,
        sharedProps: Object.freeze({ ...sharedProps }),
        currentPath,
        fieldsSchema: extractFieldsSchema(pageProps),
      };
      notify();
    },
    recordNavigation(entry: NavigationEntry): void {
      const next = state.navigationHistory.slice();
      next.push(entry);
      while (next.length > NAVIGATION_HISTORY_LIMIT) {
        next.shift();
      }
      state = {
        ...state,
        navigationHistory: Object.freeze(next),
      };
      notify();
    },
    getDevToolsPayload(): ArqelDevToolsPayload | null {
      return state.devToolsPayload;
    },
    setDevToolsPayload(payload: ArqelDevToolsPayload | null): void {
      state = {
        ...state,
        devToolsPayload: payload,
      };
      notify();
    },
    getFieldsSchema(): ReadonlyArray<FieldSchema> {
      return state.fieldsSchema;
    },
    __setState(next: ArqelDevToolsState): void {
      state = {
        ...next,
        fieldsSchema: next.fieldsSchema ?? extractFieldsSchema(next.pageProps),
      };
      notify();
    },
  };
}

/**
 * Install the Arqel DevTools hook on `window`.
 *
 * Becomes a no-op when:
 *  - `import.meta.env.DEV` is falsy (production / SSR builds);
 *  - `window` is undefined (Node SSR);
 *  - a hook is already installed (avoid double-install during HMR).
 *
 * Returns the hook instance (or `undefined` when skipped) so callers
 * can drive it directly in tests.
 */
export function installDevToolsHook(version: string): ArqelDevToolsHook | undefined {
  if (!isDevModeActive()) {
    return undefined;
  }
  if (typeof window === 'undefined') {
    return undefined;
  }
  if (window.__ARQEL_DEVTOOLS_HOOK__ !== undefined) {
    return window.__ARQEL_DEVTOOLS_HOOK__;
  }

  const hook = createDevToolsHook(version);
  window.__ARQEL_DEVTOOLS_HOOK__ = hook;
  return hook;
}

function isDevModeActive(): boolean {
  // `process.env.NODE_ENV === 'production'` always wins (this is the
  // signal both Vite and tsup honour at build time, and the one
  // `vi.stubEnv('NODE_ENV', ...)` mutates).
  if (typeof process !== 'undefined' && process.env?.['NODE_ENV'] === 'production') {
    return false;
  }
  // Otherwise consult Vite's `import.meta.env.DEV` when present.
  try {
    const env = (import.meta as ImportMeta & { env?: { DEV?: boolean | string } }).env;
    if (env && env.DEV !== undefined) {
      if (typeof env.DEV === 'boolean') {
        return env.DEV;
      }
      if (typeof env.DEV === 'string') {
        return env.DEV !== 'false' && env.DEV !== '';
      }
    }
  } catch {
    // import.meta not available — fall through.
  }
  // Last resort: NODE_ENV-driven default (dev unless explicitly prod).
  if (typeof process !== 'undefined' && process.env?.['NODE_ENV']) {
    return process.env['NODE_ENV'] !== 'production';
  }
  return true;
}

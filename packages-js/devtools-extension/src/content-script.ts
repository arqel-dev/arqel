/**
 * Arqel DevTools — content script (DEVTOOLS-002).
 *
 * Manifest V3 isolates content scripts from the page's JavaScript
 * context: `window` here is a *different* `window` than the one
 * `@arqel/react` writes to. We bridge the two worlds by injecting a
 * tiny inline `<script>` into the page DOM that probes the real
 * `window.__ARQEL_DEVTOOLS_HOOK__` and relays the result back via a
 * `CustomEvent`. The content script listens for that event and
 * forwards the payload to the background service worker.
 */
export interface ArqelDevtoolsHook {
  version: string;
}

declare global {
  interface Window {
    __ARQEL_DEVTOOLS_HOOK__?: ArqelDevtoolsHook;
  }
}

export interface DetectMessage {
  type: 'arqel.detected';
  detected: boolean;
  version: string | null;
}

export const PROBE_EVENT = 'arqel-devtools-probe';
export const STATE_REQUEST_EVENT = 'arqel-devtools-state-request';
export const STATE_RESPONSE_EVENT = 'arqel-devtools-state-response';

/** Page-world script that responds to state-request events with hook state. */
export function buildStateRelaySource(
  requestEvent: string = STATE_REQUEST_EVENT,
  responseEvent: string = STATE_RESPONSE_EVENT,
): string {
  return `(() => {
  const REQ = ${JSON.stringify(requestEvent)};
  const RES = ${JSON.stringify(responseEvent)};
  window.addEventListener(REQ, () => {
    try {
      const hook = window.__ARQEL_DEVTOOLS_HOOK__;
      const state = hook && typeof hook.getState === 'function' ? hook.getState() : null;
      // Cycles/non-serialisable values are dropped via JSON round-trip.
      const serialisable = state ? JSON.parse(JSON.stringify(state)) : null;
      window.dispatchEvent(new CustomEvent(RES, { detail: { state: serialisable } }));
    } catch (error) {
      window.dispatchEvent(new CustomEvent(RES, { detail: { state: null } }));
    }
  });
})();`;
}

/**
 * Direct (same-world) probe — used in unit tests and as a fallback
 * when the page-world script cannot be injected (e.g. CSP blocks
 * inline scripts).
 */
export function detectArqel(target: Window | undefined = globalThis.window): boolean {
  if (!target) {
    return false;
  }
  const hook = target.__ARQEL_DEVTOOLS_HOOK__;
  return Boolean(hook && typeof hook.version === 'string' && hook.version.length > 0);
}

export function buildDetectMessage(target: Window | undefined = globalThis.window): DetectMessage {
  const detected = detectArqel(target);
  const version = detected ? (target?.__ARQEL_DEVTOOLS_HOOK__?.version ?? null) : null;
  return { type: 'arqel.detected', detected, version };
}

/**
 * Build the inline source that runs in the page world. It dispatches a
 * `CustomEvent` carrying `{detected, version}` so the isolated content
 * script can read it.
 */
export function buildProbeSource(eventName: string = PROBE_EVENT): string {
  return `(() => {
  try {
    const hook = window.__ARQEL_DEVTOOLS_HOOK__;
    const detected = Boolean(hook && typeof hook.version === 'string' && hook.version.length > 0);
    const version = detected ? hook.version : null;
    window.dispatchEvent(new CustomEvent(${JSON.stringify(eventName)}, { detail: { detected, version } }));
  } catch (error) {
    window.dispatchEvent(new CustomEvent(${JSON.stringify(eventName)}, { detail: { detected: false, version: null } }));
  }
})();`;
}

export interface ProbeBridgeOptions {
  target?: Window;
  doc?: Document;
  send?: (message: DetectMessage) => void;
  /**
   * When `false`, skip injecting the inline page-world script and only
   * wire up the listener. Useful for tests that want to dispatch the
   * `CustomEvent` manually without jsdom firing the probe first.
   */
  inject?: boolean;
}

/**
 * Wire up the probe bridge: listens for the page-world CustomEvent
 * once, forwards the payload to `send` (defaults to
 * `chrome.runtime.sendMessage`), and injects the inline probe.
 *
 * Returns a teardown function that removes the listener.
 */
export function installProbeBridge(options: ProbeBridgeOptions = {}): () => void {
  const target = options.target ?? globalThis.window;
  const doc = options.doc ?? globalThis.document;
  const send =
    options.send ??
    ((message: DetectMessage): void => {
      if (typeof chrome !== 'undefined' && chrome.runtime?.sendMessage) {
        try {
          chrome.runtime.sendMessage(message);
        } catch (error) {
          console.warn('[arqel-devtools] failed to dispatch detect message', error);
        }
      }
    });

  if (!target || !doc) {
    return () => {};
  }

  const handler = (event: Event): void => {
    const detail = (event as CustomEvent<{ detected: boolean; version: string | null }>).detail;
    const detected = Boolean(detail?.detected);
    const version = detail?.version ?? null;
    send({ type: 'arqel.detected', detected, version: detected ? version : null });
  };

  target.addEventListener(PROBE_EVENT, handler, { once: true });

  if (options.inject === false) {
    return () => {
      target.removeEventListener(PROBE_EVENT, handler);
    };
  }

  // Inject the page-world probe. `script.textContent` runs synchronously
  // when appended; we drop the node afterwards to keep the DOM clean.
  try {
    const script = doc.createElement('script');
    script.textContent = buildProbeSource(PROBE_EVENT);
    (doc.head ?? doc.documentElement).appendChild(script);
    script.remove();
  } catch (error) {
    console.warn('[arqel-devtools] failed to inject page-world probe', error);
    // Fallback: same-world probe (useful when CSP blocks inline scripts).
    send(buildDetectMessage(target));
  }

  return () => {
    target.removeEventListener(PROBE_EVENT, handler);
  };
}

export interface StateRelayOptions {
  target?: Window;
  doc?: Document;
  intervalMs?: number;
  send?: (state: unknown) => void;
  inject?: boolean;
}

/**
 * DEVTOOLS-003 — install the state relay bridge.
 *
 * Periodically dispatches a state-request CustomEvent into the page
 * world; the page-world script answers via state-response, which we
 * forward to the background as `{type: 'arqel.state', state}`.
 */
export function installStateRelay(options: StateRelayOptions = {}): () => void {
  const target = options.target ?? globalThis.window;
  const doc = options.doc ?? globalThis.document;
  const intervalMs = options.intervalMs ?? 500;
  const send =
    options.send ??
    ((state: unknown): void => {
      if (typeof chrome !== 'undefined' && chrome.runtime?.sendMessage) {
        try {
          chrome.runtime.sendMessage({ type: 'arqel.state', state });
        } catch (error) {
          console.warn('[arqel-devtools] failed to relay state', error);
        }
      }
    });

  if (!target || !doc) {
    return () => {};
  }

  const handler = (event: Event): void => {
    const detail = (event as CustomEvent<{ state: unknown }>).detail;
    send(detail?.state ?? null);
  };
  target.addEventListener(STATE_RESPONSE_EVENT, handler);

  if (options.inject !== false) {
    try {
      const script = doc.createElement('script');
      script.textContent = buildStateRelaySource();
      (doc.head ?? doc.documentElement).appendChild(script);
      script.remove();
    } catch (error) {
      console.warn('[arqel-devtools] failed to inject state relay', error);
    }
  }

  const tick = (): void => {
    try {
      target.dispatchEvent(new CustomEvent(STATE_REQUEST_EVENT));
    } catch {
      // ignore
    }
  };
  tick();
  const id = setInterval(tick, intervalMs);

  return () => {
    clearInterval(id);
    target.removeEventListener(STATE_RESPONSE_EVENT, handler);
  };
}

// Auto-run when loaded as a real content script (skipped under Vitest,
// which sets `import.meta.env.MODE === 'test'`).
const env = (import.meta as ImportMeta & { env?: { MODE?: string; VITEST?: boolean } }).env;
const isTest = env?.MODE === 'test' || env?.VITEST === true;

if (!isTest && typeof chrome !== 'undefined' && chrome.runtime) {
  installProbeBridge();
  installStateRelay();
}

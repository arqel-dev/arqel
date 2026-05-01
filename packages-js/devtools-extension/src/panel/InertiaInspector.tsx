/**
 * Inertia state inspector panel (DEVTOOLS-003).
 *
 * The panel runs in the DevTools page context and cannot reach the
 * inspected page directly. State arrives via a long-lived
 * `chrome.runtime.connect` port to the background service worker, which
 * relays content-script readings of `window.__ARQEL_DEVTOOLS_HOOK__`.
 *
 * Falls back to a one-shot `chrome.runtime.sendMessage({type:
 * 'arqel.getState'})` when the long-lived port is unavailable (e.g.
 * unit tests that mock only `sendMessage`).
 */
import { useEffect, useMemo, useState } from 'react';
import { JsonNode } from './JsonNode.js';

export interface NavigationEntry {
  readonly path: string;
  readonly timestamp: number;
  readonly durationMs?: number;
}

export interface ArqelState {
  readonly panel: string | null;
  readonly resource: string | null;
  readonly sharedProps: Record<string, unknown>;
  readonly pageProps: unknown;
  readonly currentPath: string;
  readonly navigationHistory: ReadonlyArray<NavigationEntry>;
}

const EMPTY_STATE: ArqelState = {
  panel: null,
  resource: null,
  sharedProps: {},
  pageProps: null,
  currentPath: '',
  navigationHistory: [],
};

type Tab = 'pageProps' | 'sharedProps' | 'navigation';

export interface InertiaInspectorProps {
  /** Override the state source — used in tests to inject fixtures. */
  readonly stateSource?: (cb: (state: ArqelState) => void) => () => void;
  /** Override `navigator.clipboard.writeText` — useful for tests. */
  readonly writeClipboard?: (text: string) => Promise<void>;
}

export function InertiaInspector({ stateSource, writeClipboard }: InertiaInspectorProps = {}) {
  const [state, setState] = useState<ArqelState>(EMPTY_STATE);
  const [tab, setTab] = useState<Tab>('pageProps');
  const [search, setSearch] = useState('');
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    const source = stateSource ?? defaultStateSource;
    return source(setState);
  }, [stateSource]);

  const filteredHistory = useMemo(() => {
    const term = search.trim().toLowerCase();
    if (term === '') return state.navigationHistory;
    return state.navigationHistory.filter((e) => e.path.toLowerCase().includes(term));
  }, [state.navigationHistory, search]);

  async function handleCopy() {
    const writer = writeClipboard ?? defaultWriteClipboard;
    try {
      await writer(JSON.stringify(state, null, 2));
      setCopied(true);
      setTimeout(() => setCopied(false), 1200);
    } catch (error) {
      console.warn('[arqel-devtools] clipboard write failed', error);
    }
  }

  return (
    <div data-testid="arqel-inertia-inspector" className="arqel-inertia-inspector">
      <header className="arqel-inspector-header">
        <div role="tablist" className="arqel-tabs">
          <button
            type="button"
            role="tab"
            aria-selected={tab === 'pageProps'}
            data-testid="tab-page-props"
            onClick={() => setTab('pageProps')}
          >
            Page Props
          </button>
          <button
            type="button"
            role="tab"
            aria-selected={tab === 'sharedProps'}
            data-testid="tab-shared-props"
            onClick={() => setTab('sharedProps')}
          >
            Shared Props
          </button>
          <button
            type="button"
            role="tab"
            aria-selected={tab === 'navigation'}
            data-testid="tab-navigation"
            onClick={() => setTab('navigation')}
          >
            Navigation History
          </button>
        </div>
        <div className="arqel-inspector-controls">
          <input
            type="search"
            placeholder="Filter…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            data-testid="inspector-search"
            aria-label="Filter inspector"
          />
          <button
            type="button"
            onClick={handleCopy}
            data-testid="inspector-copy"
            aria-label="Copy state JSON"
          >
            {copied ? 'Copied!' : 'Copy'}
          </button>
        </div>
      </header>
      <section className="arqel-inspector-body" role="tabpanel">
        {tab === 'pageProps' && (
          <JsonNode value={state.pageProps} path="pageProps" search={search} defaultExpanded />
        )}
        {tab === 'sharedProps' && (
          <JsonNode value={state.sharedProps} path="sharedProps" search={search} defaultExpanded />
        )}
        {tab === 'navigation' && (
          <ol className="arqel-nav-history" data-testid="navigation-list">
            {filteredHistory.length === 0 && (
              <li className="arqel-nav-empty">No navigation events recorded.</li>
            )}
            {filteredHistory.map((entry, idx) => (
              // biome-ignore lint/suspicious/noArrayIndexKey: timestamps can collide across rapid navigations; index disambiguates within a snapshot.
              <li key={`${entry.timestamp}-${entry.path}-${idx}`} data-testid="navigation-entry">
                <code>{entry.path || '<unknown>'}</code>
                <span className="arqel-nav-timestamp">
                  {new Date(entry.timestamp).toLocaleTimeString()}
                </span>
                {entry.durationMs !== undefined && (
                  <span className="arqel-nav-duration">{entry.durationMs}ms</span>
                )}
              </li>
            ))}
          </ol>
        )}
      </section>
    </div>
  );
}

interface RuntimeMessage {
  type: 'arqel.state';
  state: ArqelState;
}

function defaultStateSource(cb: (state: ArqelState) => void): () => void {
  if (typeof chrome === 'undefined' || !chrome.runtime) {
    return () => {};
  }

  // Preferred path: long-lived port for push updates.
  let port: chrome.runtime.Port | undefined;
  try {
    port = chrome.runtime.connect({ name: 'arqel-devtools-panel' });
  } catch {
    port = undefined;
  }

  if (port) {
    const onMessage = (msg: RuntimeMessage) => {
      if (msg && msg.type === 'arqel.state' && msg.state) {
        cb(msg.state);
      }
    };
    port.onMessage.addListener(onMessage);
    try {
      port.postMessage({ type: 'arqel.getState' });
    } catch {
      // ignore — disconnected port
    }
    return () => {
      try {
        port?.disconnect();
      } catch {
        // ignore
      }
    };
  }

  // Fallback: poll via sendMessage.
  let cancelled = false;
  const interval = setInterval(() => {
    if (cancelled) return;
    try {
      chrome.runtime.sendMessage({ type: 'arqel.getState' }, (response: RuntimeMessage) => {
        if (!cancelled && response && response.type === 'arqel.state' && response.state) {
          cb(response.state);
        }
      });
    } catch {
      // ignore
    }
  }, 250);
  return () => {
    cancelled = true;
    clearInterval(interval);
  };
}

async function defaultWriteClipboard(text: string): Promise<void> {
  if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(text);
    return;
  }
  throw new Error('navigator.clipboard unavailable');
}

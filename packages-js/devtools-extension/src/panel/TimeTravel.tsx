/**
 * Time-travel debugging panel (DEVTOOLS-006).
 *
 * Renders the navigation snapshots ring buffer captured by the in-page
 * `@arqel/react` hook. Each row shows the URL, a relative timestamp
 * and (when present) the visit duration. Clicking a row expands its
 * `pageProps` via `<JsonNode>`. The "Replay" action emits a
 * `chrome.runtime.sendMessage({ type: 'arqel.replay' })` event so a
 * future content-script handler can drive Inertia to revisit the URL.
 *
 * The panel never mutates page state directly — replay is opt-in and
 * inert until the runtime side wires it up.
 */
import { useState } from 'react';
import { JsonNode } from './JsonNode.js';

export interface NavigationSnapshot {
  readonly id: string;
  readonly timestamp: number;
  readonly url: string;
  readonly pageProps: unknown;
  readonly sharedProps: Readonly<Record<string, unknown>>;
  readonly durationMs?: number;
}

export interface TimeTravelProps {
  readonly snapshots: ReadonlyArray<NavigationSnapshot>;
  /** Override for tests — defaults to `chrome.runtime.sendMessage`. */
  readonly onReplay?: (snapshot: NavigationSnapshot) => void;
}

const SLOW_THRESHOLD_MS = 100;

export function TimeTravel({ snapshots, onReplay }: TimeTravelProps) {
  const [expandedId, setExpandedId] = useState<string | null>(null);

  if (snapshots.length === 0) {
    return (
      <div data-testid="arqel-time-travel" className="arqel-time-travel">
        <p data-testid="time-travel-empty" className="arqel-tt-empty">
          No navigation snapshots captured yet. Navigate within the app to record state.
        </p>
      </div>
    );
  }

  function handleReplay(snapshot: NavigationSnapshot) {
    const handler = onReplay ?? defaultReplay;
    handler(snapshot);
  }

  return (
    <div data-testid="arqel-time-travel" className="arqel-time-travel">
      <header className="arqel-tt-header">
        <span data-testid="time-travel-counter" className="arqel-tt-counter">
          {snapshots.length} snapshot{snapshots.length === 1 ? '' : 's'}
        </span>
      </header>
      <ol className="arqel-tt-list" data-testid="time-travel-list">
        {snapshots.map((snap) => {
          const isOpen = expandedId === snap.id;
          const isSlow =
            typeof snap.durationMs === 'number' && snap.durationMs >= SLOW_THRESHOLD_MS;
          return (
            <li
              key={snap.id}
              data-testid="time-travel-entry"
              data-snapshot-id={snap.id}
              data-slow={isSlow ? 'true' : 'false'}
            >
              <div className="arqel-tt-row">
                <button
                  type="button"
                  className="arqel-tt-toggle"
                  aria-expanded={isOpen}
                  data-testid="time-travel-toggle"
                  onClick={() => setExpandedId(isOpen ? null : snap.id)}
                >
                  <span aria-hidden="true">{isOpen ? '▼' : '▶'}</span>
                  <code className="arqel-tt-url">{snap.url || '<unknown>'}</code>
                  <span className="arqel-tt-time" data-testid="time-travel-timestamp">
                    {new Date(snap.timestamp).toLocaleTimeString()}
                  </span>
                  {snap.durationMs !== undefined && (
                    <span
                      className={`arqel-tt-duration${isSlow ? ' arqel-tt-duration--slow' : ''}`}
                      data-testid="time-travel-duration"
                    >
                      {snap.durationMs}ms
                    </span>
                  )}
                </button>
                <button
                  type="button"
                  className="arqel-tt-replay"
                  data-testid="time-travel-replay"
                  onClick={() => handleReplay(snap)}
                  aria-label={`Replay ${snap.url}`}
                >
                  Replay
                </button>
              </div>
              {isOpen && (
                <div className="arqel-tt-detail" data-testid="time-travel-detail">
                  <h4>pageProps</h4>
                  <JsonNode value={snap.pageProps} path={`snap-${snap.id}-page`} defaultExpanded />
                  <h4>sharedProps</h4>
                  <JsonNode
                    value={snap.sharedProps}
                    path={`snap-${snap.id}-shared`}
                    defaultExpanded={false}
                  />
                </div>
              )}
            </li>
          );
        })}
      </ol>
    </div>
  );
}

function defaultReplay(snapshot: NavigationSnapshot): void {
  // Best-effort dispatch through chrome.runtime — handled by future
  // content-script. Also fire a window-level CustomEvent so panel
  // tests / observers can react without the chrome runtime.
  try {
    if (typeof chrome !== 'undefined' && chrome.runtime?.sendMessage) {
      chrome.runtime.sendMessage({
        type: 'arqel.replay',
        payload: { url: snapshot.url, id: snapshot.id },
      });
    }
  } catch {
    // ignore — the runtime may have torn down.
  }
  if (typeof window !== 'undefined' && typeof window.dispatchEvent === 'function') {
    try {
      window.dispatchEvent(
        new CustomEvent('arqel-devtools-replay', {
          detail: { url: snapshot.url, id: snapshot.id },
        }),
      );
    } catch {
      // ignore — older runtimes without CustomEvent.
    }
  }
}

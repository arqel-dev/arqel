/**
 * Arqel DevTools — Performance metrics observer (DEVTOOLS-007).
 *
 * Subscribes to `PerformanceObserver` entry types relevant to Web
 * Vitals (LCP, FID, INP, CLS) plus the initial navigation timing, and
 * forwards them to the DevTools hook via `recordPerformanceMetric`.
 *
 * SSR-safe: bails out when `window` or `PerformanceObserver` are
 * unavailable. Like the rest of the devtools surface, this entire
 * module MUST stay DEV-only — callers gate the install behind
 * `installDevToolsHook` having returned a hook.
 */
import type { ArqelDevToolsHook } from './devtools.js';

/**
 * Disposer returned by {@link installPerformanceObserver}. Calling it
 * disconnects every observer registered on install.
 */
export type PerformanceObserverDisposer = () => void;

interface InstallOptions {
  /** Override the global `PerformanceObserver` ctor — used in tests. */
  readonly observerCtor?: typeof PerformanceObserver;
  /** Override the global `performance` instance — used in tests. */
  readonly performance?: Performance;
}

interface CLSEntry extends PerformanceEntry {
  readonly value: number;
  readonly hadRecentInput?: boolean;
}

interface EventTimingEntry extends PerformanceEntry {
  readonly processingStart?: number;
}

/**
 * Wires up `PerformanceObserver`s for Web Vitals and forwards results
 * to the DevTools hook. Returns a disposer; the disposer is a no-op
 * when the environment doesn't support observation.
 */
export function installPerformanceObserver(
  hook: ArqelDevToolsHook,
  options: InstallOptions = {},
): PerformanceObserverDisposer {
  if (typeof window === 'undefined') return () => {};
  const Observer = options.observerCtor ?? globalThis.PerformanceObserver;
  if (typeof Observer !== 'function') return () => {};
  const perf = options.performance ?? globalThis.performance;

  const observers: PerformanceObserver[] = [];

  // Initial navigation timing (one-shot).
  if (perf?.getEntriesByType) {
    try {
      const [nav] = perf.getEntriesByType('navigation') as PerformanceNavigationTiming[];
      if (nav && typeof nav.duration === 'number' && nav.duration > 0) {
        hook.recordPerformanceMetric('navigationTime', nav.duration);
      }
    } catch {
      // ignore — feature-detect failed.
    }
  }

  observe(Observer, observers, ['largest-contentful-paint'], (entries) => {
    const last = entries[entries.length - 1];
    if (last) {
      hook.recordPerformanceMetric('lcp', last.startTime);
    }
  });

  observe(Observer, observers, ['first-input'], (entries) => {
    const first = entries[0] as EventTimingEntry | undefined;
    if (first && typeof first.processingStart === 'number') {
      hook.recordPerformanceMetric('fid', first.processingStart - first.startTime);
    }
  });

  observe(Observer, observers, ['event'], (entries) => {
    // INP approximation — pick the worst event timing duration so far.
    let worst = hook.getPerformanceMetrics().inp ?? 0;
    for (const entry of entries) {
      if (entry.duration > worst) worst = entry.duration;
    }
    if (worst > 0) {
      hook.recordPerformanceMetric('inp', worst);
    }
  });

  observe(Observer, observers, ['layout-shift'], (entries) => {
    let cumulative = hook.getPerformanceMetrics().cls ?? 0;
    for (const entry of entries as CLSEntry[]) {
      if (entry.hadRecentInput) continue;
      cumulative += entry.value;
    }
    hook.recordPerformanceMetric('cls', cumulative);
  });

  observe(Observer, observers, ['paint'], (entries) => {
    // We don't expose FCP separately, but if navigationTime is still
    // null, use the first contentful paint as an early signal.
    if (hook.getPerformanceMetrics().navigationTime !== null) return;
    const fcp = entries.find((e) => e.name === 'first-contentful-paint');
    if (fcp) {
      hook.recordPerformanceMetric('navigationTime', fcp.startTime);
    }
  });

  return () => {
    for (const obs of observers) {
      try {
        obs.disconnect();
      } catch {
        // ignore — already disconnected.
      }
    }
  };
}

function observe(
  Observer: typeof PerformanceObserver,
  registry: PerformanceObserver[],
  entryTypes: string[],
  callback: (entries: PerformanceEntryList) => void,
): void {
  try {
    const observer = new Observer((list) => {
      callback(list.getEntries());
    });
    observer.observe({ type: entryTypes[0] as string, buffered: true });
    registry.push(observer);
  } catch {
    // Browser doesn't support this entry type — ignore.
  }
}

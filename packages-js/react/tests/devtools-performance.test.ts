import { describe, expect, it, vi } from 'vitest';

import { createDevToolsHook } from '../src/devtools/devtools.js';
import { installPerformanceObserver } from '../src/devtools/performance.js';

interface FakeEntry {
  startTime: number;
  duration: number;
  name?: string;
  processingStart?: number;
  value?: number;
  hadRecentInput?: boolean;
}

function fakeObserverCtor() {
  const observers: Array<{
    type: string;
    callback: (list: { getEntries: () => FakeEntry[] }) => void;
    disconnect: ReturnType<typeof vi.fn>;
    feed: (entries: FakeEntry[]) => void;
  }> = [];

  class FakeObserver {
    private cb: (list: { getEntries: () => FakeEntry[] }) => void;
    constructor(cb: (list: { getEntries: () => FakeEntry[] }) => void) {
      this.cb = cb;
    }
    observe(opts: { type: string }) {
      const entry = {
        type: opts.type,
        callback: this.cb,
        disconnect: vi.fn(),
        feed: (entries: FakeEntry[]) => this.cb({ getEntries: () => entries }),
      };
      observers.push(entry);
    }
    disconnect() {
      // no-op for the harness; observe() returns a fresh disconnect spy.
    }
  }

  return { Ctor: FakeObserver as unknown as typeof PerformanceObserver, observers };
}

describe('installPerformanceObserver (DEVTOOLS-007)', () => {
  it('registers observers for Web Vitals entry types', () => {
    const hook = createDevToolsHook('test');
    const { Ctor, observers } = fakeObserverCtor();
    installPerformanceObserver(hook, { observerCtor: Ctor });
    const types = observers.map((o) => o.type);
    expect(types).toContain('largest-contentful-paint');
    expect(types).toContain('first-input');
    expect(types).toContain('event');
    expect(types).toContain('layout-shift');
  });

  it('forwards LCP and CLS entries to the hook', () => {
    const hook = createDevToolsHook('test');
    const { Ctor, observers } = fakeObserverCtor();
    installPerformanceObserver(hook, { observerCtor: Ctor });

    const lcp = observers.find((o) => o.type === 'largest-contentful-paint');
    lcp?.feed([{ startTime: 1234, duration: 0 }]);

    const cls = observers.find((o) => o.type === 'layout-shift');
    cls?.feed([
      { startTime: 0, duration: 0, value: 0.05, hadRecentInput: false },
      { startTime: 0, duration: 0, value: 0.02, hadRecentInput: false },
    ]);

    const metrics = hook.getPerformanceMetrics();
    expect(metrics.lcp).toBe(1234);
    expect(metrics.cls).toBeCloseTo(0.07, 5);
  });

  it('recordPerformanceMetric updates the state shape', () => {
    const hook = createDevToolsHook('test');
    hook.recordPerformanceMetric('lcp', 1500);
    hook.recordPerformanceMetric('inp', 220);
    hook.recordPerformanceMetric('cls', 0.05);
    hook.recordPerformanceMetric('navigationTime', 800);
    expect(hook.getPerformanceMetrics()).toEqual({
      lcp: 1500,
      inp: 220,
      fid: null,
      cls: 0.05,
      navigationTime: 800,
    });
  });

  it('is SSR-safe — no-op when PerformanceObserver is unavailable', () => {
    const hook = createDevToolsHook('test');
    const dispose = installPerformanceObserver(hook, {
      observerCtor: undefined as unknown as typeof PerformanceObserver,
    });
    expect(typeof dispose).toBe('function');
    expect(hook.getPerformanceMetrics()).toEqual({
      lcp: null,
      inp: null,
      fid: null,
      cls: null,
      navigationTime: null,
    });
    // Disposer is callable without throwing.
    expect(() => dispose()).not.toThrow();
  });
});

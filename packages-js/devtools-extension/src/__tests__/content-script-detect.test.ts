import { afterEach, describe, expect, it, vi } from 'vitest';

import {
  buildProbeSource,
  type DetectMessage,
  installProbeBridge,
  PROBE_EVENT,
} from '../content-script';

afterEach(() => {
  delete (window as Window).__ARQEL_DEVTOOLS_HOOK__;
  for (const node of Array.from(document.head.querySelectorAll('script[data-arqel-probe]'))) {
    node.remove();
  }
});

describe('buildProbeSource', () => {
  it('emits a self-invoking IIFE that dispatches the probe event', () => {
    const source = buildProbeSource();
    expect(source).toContain('window.__ARQEL_DEVTOOLS_HOOK__');
    expect(source).toContain(PROBE_EVENT);
    expect(source).toContain('CustomEvent');
  });
});

describe('installProbeBridge', () => {
  it('forwards a detected payload from the page-world CustomEvent to send()', () => {
    const send = vi.fn<(msg: DetectMessage) => void>();
    installProbeBridge({ target: window, doc: document, send, inject: false });

    window.dispatchEvent(
      new CustomEvent(PROBE_EVENT, { detail: { detected: true, version: '1.2.3' } }),
    );

    expect(send).toHaveBeenCalledTimes(1);
    expect(send).toHaveBeenCalledWith({
      type: 'arqel.detected',
      detected: true,
      version: '1.2.3',
    });
  });

  it('reports `detected: false` when the page-world responds negatively', () => {
    const send = vi.fn<(msg: DetectMessage) => void>();
    installProbeBridge({ target: window, doc: document, send, inject: false });

    window.dispatchEvent(
      new CustomEvent(PROBE_EVENT, { detail: { detected: false, version: null } }),
    );

    expect(send).toHaveBeenCalledWith({
      type: 'arqel.detected',
      detected: false,
      version: null,
    });
  });

  it('injects an inline <script> in the document head when inject is enabled', () => {
    const send = vi.fn<(msg: DetectMessage) => void>();
    const created: HTMLScriptElement[] = [];
    const origCreateElement = document.createElement.bind(document);
    const spy = vi.spyOn(document, 'createElement').mockImplementation((tagName: string) => {
      const el = origCreateElement(tagName) as HTMLScriptElement;
      if (tagName === 'script') {
        created.push(el);
      }
      return el;
    });

    installProbeBridge({ target: window, doc: document, send });

    expect(created).toHaveLength(1);
    expect(created[0]?.textContent).toContain(PROBE_EVENT);
    spy.mockRestore();
  });

  it('listens only once per install — second event is ignored', () => {
    const send = vi.fn<(msg: DetectMessage) => void>();
    installProbeBridge({ target: window, doc: document, send, inject: false });

    window.dispatchEvent(
      new CustomEvent(PROBE_EVENT, { detail: { detected: true, version: 'x' } }),
    );
    window.dispatchEvent(
      new CustomEvent(PROBE_EVENT, { detail: { detected: true, version: 'y' } }),
    );
    expect(send).toHaveBeenCalledTimes(1);
  });
});

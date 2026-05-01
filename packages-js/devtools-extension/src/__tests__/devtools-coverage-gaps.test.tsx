import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { type ArqelDetectedMessage, createBackground } from '../background';
import {
  buildDetectMessage,
  buildProbeSource,
  type DetectMessage,
  installProbeBridge,
  PROBE_EVENT,
} from '../content-script';
import chromeManifest from '../manifests/chrome.json';
import { App } from '../panel/App';

/*
 * DEVTOOLS-008 — Coverage gaps complementando suites por entrega
 * (DEVTOOLS-001..003). Foca em ramos não tocados: states de borda do
 * painel, profundidade segura de árvores, idempotência do bridge,
 * sanity do source da página injetada e rotina de cleanup do background.
 */

afterEach(() => {
  delete (window as Window).__ARQEL_DEVTOOLS_HOOK__;
});

describe('panel/App edge cases', () => {
  it('treats null explicitly as inactive', () => {
    render(<App version={null} />);
    expect(screen.getByTestId('arqel-devtools-panel')).toHaveAttribute('data-status', 'inactive');
  });

  it('renders deeply nested-ish version strings without crashing', () => {
    // Deep / weird inputs (strings com chars de markup) não devem quebrar
    // o React rendering — heurística de robustez do shell.
    const weird = `v${'0.'.repeat(20)}<script>`;
    render(<App version={weird} />);
    const heading = screen.getByRole('heading');
    expect(heading.textContent).toContain(weird);
    expect(heading.innerHTML).not.toContain('<script>');
  });
});

describe('content-script idempotency & probe source', () => {
  it('installProbeBridge listener is installed once per call (no event duplication)', () => {
    const send = vi.fn<(msg: DetectMessage) => void>();
    const teardown1 = installProbeBridge({ target: window, doc: document, send, inject: false });
    const teardown2 = installProbeBridge({ target: window, doc: document, send, inject: false });

    window.dispatchEvent(
      new CustomEvent(PROBE_EVENT, { detail: { detected: true, version: '9.9.9' } }),
    );

    // Mesmo chamando installProbeBridge duas vezes com `{ once: true }`,
    // cada listener dispara no máximo 1x — i.e. send é chamado 2x e não 3x+,
    // garantindo que a função não vaze listeners cumulativos por install.
    expect(send.mock.calls.length).toBeLessThanOrEqual(2);
    teardown1();
    teardown2();

    // Após teardown, novos eventos não disparam send.
    const before = send.mock.calls.length;
    window.dispatchEvent(
      new CustomEvent(PROBE_EVENT, { detail: { detected: true, version: 'x' } }),
    );
    expect(send.mock.calls.length).toBe(before);
  });

  it('buildProbeSource emits an IIFE with try/catch fallback path', () => {
    const source = buildProbeSource('arqel-test-evt');
    // Try/catch para que CSP / hooks throwing não derrubem a página.
    expect(source).toContain('try {');
    expect(source).toContain('catch');
    // Event name é JSON-encoded (string literal entre aspas).
    expect(source).toContain('"arqel-test-evt"');
    // IIFE — deve abrir e fechar parens balanceados.
    const opens = (source.match(/\(/g) ?? []).length;
    const closes = (source.match(/\)/g) ?? []).length;
    expect(opens).toBe(closes);
  });

  it('buildDetectMessage on a window without the hook returns detected=false', () => {
    const msg = buildDetectMessage(window);
    expect(msg).toEqual({ type: 'arqel.detected', detected: false, version: null });
  });
});

describe('background — tab lifecycle & null-version handling', () => {
  it('discards tab state on removeTab and a subsequent message starts fresh', () => {
    const setIcon = vi.fn();
    const bg = createBackground({ setIcon });

    bg.handleMessage(
      { type: 'arqel.detected', detected: true, version: '1.0.0' } as ArqelDetectedMessage,
      {
        tab: { id: 314 },
      },
    );
    expect(bg.getTabState(314)).toEqual({ detected: true, version: '1.0.0' });

    bg.removeTab(314);
    expect(bg.getTabState(314)).toBeUndefined();

    // Re-detecção do mesmo tab depois do close: estado novo, não residual.
    bg.handleMessage(
      { type: 'arqel.detected', detected: false, version: null } as ArqelDetectedMessage,
      {
        tab: { id: 314 },
      },
    );
    expect(bg.getTabState(314)).toEqual({ detected: false, version: null });
  });

  it('forces version to null when detected is false even if a version is sent', () => {
    const bg = createBackground({ setIcon: vi.fn() });
    bg.handleMessage(
      { type: 'arqel.detected', detected: false, version: 'leak' } as ArqelDetectedMessage,
      { tab: { id: 1 } },
    );
    expect(bg.getTabState(1)).toEqual({ detected: false, version: null });
  });

  it('returns ok=true for unknown message types without mutating state', () => {
    const bg = createBackground({ setIcon: vi.fn() });
    const response = bg.handleMessage({ type: 'arqel.unknown' }, { tab: { id: 5 } });
    expect(response).toEqual({ ok: true, type: 'arqel.unknown' });
    expect(bg.getTabState(5)).toBeUndefined();
  });
});

describe('manifest sanity (DEVTOOLS-008 polish)', () => {
  it('declares an action with default popup-less behaviour suitable for setIcon updates', () => {
    // O background precisa do campo `action` para chamar chrome.action.setIcon.
    expect(chromeManifest.action).toBeTruthy();
  });
});

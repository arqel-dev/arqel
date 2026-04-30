import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// Mocks precisam ser hoisted antes da import do módulo testado;
// `vi.hoisted` garante que `echoCtor` / `pusherSentinel` existam no
// momento em que as factories de `vi.mock` são executadas.
const { echoCtor, pusherSentinel } = vi.hoisted(() => ({
  echoCtor: vi.fn(),
  pusherSentinel: { __pusher: true },
}));

vi.mock('laravel-echo', () => ({
  default: vi.fn().mockImplementation((opts: unknown) => {
    echoCtor(opts);
    return { __echo: true, opts };
  }),
}));

vi.mock('pusher-js', () => ({
  default: pusherSentinel,
}));

import { setupEcho } from './setupEcho';

interface WindowWithEcho {
  Echo?: unknown;
  Pusher?: unknown;
}

describe('setupEcho', () => {
  beforeEach(() => {
    echoCtor.mockClear();
    const w = window as unknown as WindowWithEcho;
    delete w.Echo;
    delete w.Pusher;
  });

  afterEach(() => {
    const w = window as unknown as WindowWithEcho;
    delete w.Echo;
    delete w.Pusher;
  });

  it('cria window.Echo e attacha window.Pusher no happy path', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

    setupEcho({ key: 'app-key-123' });

    const w = window as unknown as WindowWithEcho;
    expect(w.Echo).toBeDefined();
    expect(w.Pusher).toBe(pusherSentinel);
    expect(echoCtor).toHaveBeenCalledTimes(1);
    expect(warn).not.toHaveBeenCalled();
  });

  it('passa config (incluindo key) para o construtor do Echo com broadcaster reverb', () => {
    setupEcho({
      key: 'reverb-key-xyz',
      wsHost: 'ws.example.com',
      wsPort: 6001,
      forceTLS: true,
    });

    expect(echoCtor).toHaveBeenCalledWith({
      broadcaster: 'reverb',
      key: 'reverb-key-xyz',
      wsHost: 'ws.example.com',
      wsPort: 6001,
      forceTLS: true,
    });
  });

  it('é idempotente — segunda chamada não recria o Echo', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

    setupEcho({ key: 'first' });
    const firstInstance = (window as unknown as WindowWithEcho).Echo;

    setupEcho({ key: 'second' });
    const secondInstance = (window as unknown as WindowWithEcho).Echo;

    expect(secondInstance).toBe(firstInstance);
    expect(echoCtor).toHaveBeenCalledTimes(1);
    expect(warn).toHaveBeenCalledWith(expect.stringContaining('window.Echo already initialized'));
  });

  it('é SSR-safe — sem window, não crasha e emite warning', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
    const originalWindow = globalThis.window;

    // Simula ambiente SSR (Node-only).
    // biome-ignore lint/suspicious/noExplicitAny: removendo globalThis.window controlado
    delete (globalThis as any).window;

    expect(() => setupEcho({ key: 'ssr-key' })).not.toThrow();
    expect(echoCtor).not.toHaveBeenCalled();
    expect(warn).toHaveBeenCalledWith(expect.stringContaining('SSR'));

    // Restaura window para os próximos testes.
    // biome-ignore lint/suspicious/noExplicitAny: restaurando globalThis.window
    (globalThis as any).window = originalWindow;
  });
});

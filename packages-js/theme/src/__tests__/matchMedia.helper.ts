import { vi } from 'vitest';

interface MockMedia {
  matches: boolean;
  listeners: Array<() => void>;
  setMatches(value: boolean): void;
}

/**
 * Helper para testes: instala matchMedia controlável.
 */
export function installMatchMedia(initialDark = false): MockMedia {
  const state: MockMedia = {
    matches: initialDark,
    listeners: [],
    setMatches(value: boolean) {
      state.matches = value;
      for (const l of state.listeners) l();
    },
  };

  const media = {
    get matches() {
      return state.matches;
    },
    media: '(prefers-color-scheme: dark)',
    onchange: null,
    addEventListener: (_e: string, cb: () => void) => {
      state.listeners.push(cb);
    },
    removeEventListener: (_e: string, cb: () => void) => {
      state.listeners = state.listeners.filter((l) => l !== cb);
    },
    addListener: vi.fn(),
    removeListener: vi.fn(),
    dispatchEvent: vi.fn(),
  };

  // jsdom does not define `window.matchMedia`, and Vitest 4's `vi.spyOn`
  // refuses to spy on a non-function. Install a stub first when absent so
  // the spy has something to replace.
  if (typeof window.matchMedia !== 'function') {
    window.matchMedia = (() => media) as unknown as typeof window.matchMedia;
  }
  vi.spyOn(window, 'matchMedia').mockImplementation(() => media as unknown as MediaQueryList);
  return state;
}

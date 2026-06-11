import { act, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { computeInitialResolved, ThemeProvider, useTheme } from '../src/providers/ThemeProvider.js';

function ThemeProbe() {
  const { theme, resolved, setTheme, toggle } = useTheme();
  return (
    <div>
      <span data-testid="theme">{theme}</span>
      <span data-testid="resolved">{resolved}</span>
      <button type="button" onClick={() => setTheme('dark')} data-testid="set-dark">
        dark
      </button>
      <button type="button" onClick={toggle} data-testid="toggle">
        toggle
      </button>
    </div>
  );
}

/**
 * Stub `window.matchMedia` (absent in this test environment). `prefersDark`
 * controls what `(prefers-color-scheme: dark)` reports, so a `system` theme
 * resolves deterministically.
 */
function mockMatchMedia(prefersDark: boolean): void {
  window.matchMedia = ((query: string) => ({
    matches: prefersDark && query.includes('dark'),
    media: query,
    addEventListener: () => {},
    removeEventListener: () => {},
    addListener: () => {},
    removeListener: () => {},
    onchange: null,
    dispatchEvent: () => false,
  })) as unknown as typeof window.matchMedia;
}

describe('ThemeProvider', () => {
  beforeEach(() => {
    window.localStorage.clear();
    document.documentElement.classList.remove('light', 'dark');
    document.documentElement.removeAttribute('data-theme');
    mockMatchMedia(false);
  });

  afterEach(() => {
    window.localStorage.clear();
    document.documentElement.classList.remove('light', 'dark');
    document.documentElement.removeAttribute('data-theme');
  });

  it('starts at the default theme', () => {
    render(
      <ThemeProvider defaultTheme="light">
        <ThemeProbe />
      </ThemeProvider>,
    );

    expect(screen.getByTestId('theme')).toHaveTextContent('light');
  });

  it('setTheme persists to localStorage', () => {
    render(
      <ThemeProvider defaultTheme="light">
        <ThemeProbe />
      </ThemeProvider>,
    );

    act(() => {
      screen.getByTestId('set-dark').click();
    });

    expect(window.localStorage.getItem('arqel-theme')).toBe('dark');
    expect(screen.getByTestId('theme')).toHaveTextContent('dark');
  });

  it('toggle flips between resolved values', () => {
    render(
      <ThemeProvider defaultTheme="light">
        <ThemeProbe />
      </ThemeProvider>,
    );

    expect(screen.getByTestId('resolved')).toHaveTextContent('light');

    act(() => {
      screen.getByTestId('toggle').click();
    });

    expect(screen.getByTestId('resolved')).toHaveTextContent('dark');
  });

  it('useTheme throws outside a provider', () => {
    expect(() => render(<ThemeProbe />)).toThrow(/no ThemeProvider/i);
  });

  // Issue #247: the FOUC guard applies `dark` to <html> before React mounts;
  // `resolved` must read that on the FIRST render (not after an effect tick)
  // so the toggle icon/aria-label are correct on first paint.
  it('initializes resolved from the dark class already on <html>', () => {
    document.documentElement.classList.add('dark');
    mockMatchMedia(true); // system prefers dark, matching the applied class

    render(
      <ThemeProvider defaultTheme="system">
        <ThemeProbe />
      </ThemeProvider>,
    );

    expect(screen.getByTestId('resolved')).toHaveTextContent('dark');
  });

  it('initializes resolved from a stored dark preference (no class yet)', () => {
    window.localStorage.setItem('arqel-theme', 'dark');

    render(
      <ThemeProvider defaultTheme="light">
        <ThemeProbe />
      </ThemeProvider>,
    );

    expect(screen.getByTestId('resolved')).toHaveTextContent('dark');
  });

  it('initializes resolved from the data-theme attribute', () => {
    document.documentElement.setAttribute('data-theme', 'dark');
    mockMatchMedia(true); // system prefers dark, matching the applied attribute

    render(
      <ThemeProvider defaultTheme="system" attribute="data-theme">
        <ThemeProbe />
      </ThemeProvider>,
    );

    expect(screen.getByTestId('resolved')).toHaveTextContent('dark');
  });
});

// Direct unit tests of the first-paint resolver in ISOLATION from the
// post-mount effect (which would otherwise mask the initializer). The
// decisive case is when the applied DOM diverges from what the effect would
// resolve — only the initializer reading the DOM can produce the right value.
describe('computeInitialResolved', () => {
  beforeEach(() => {
    window.localStorage.clear();
    document.documentElement.classList.remove('light', 'dark');
    document.documentElement.removeAttribute('data-theme');
    mockMatchMedia(false);
  });

  afterEach(() => {
    document.documentElement.classList.remove('light', 'dark');
    document.documentElement.removeAttribute('data-theme');
  });

  const opts = {
    storageKey: 'arqel-theme',
    darkClass: 'dark',
    attribute: 'class' as const,
    defaultTheme: 'light' as const,
  };

  it('reads the applied dark class even when defaultTheme + system would say light', () => {
    // FOUC applied `dark`, but defaultTheme is light AND the system prefers
    // light — so ONLY the DOM read can yield 'dark'. This is what isolates
    // the lazy initializer from the effect.
    document.documentElement.classList.add('dark');

    expect(computeInitialResolved(opts)).toBe('dark');
  });

  it('reads the applied data-theme attribute over the default', () => {
    document.documentElement.setAttribute('data-theme', 'dark');

    expect(computeInitialResolved({ ...opts, attribute: 'data-theme' })).toBe('dark');
  });

  it('falls back to a stored preference when no class/attribute is applied', () => {
    window.localStorage.setItem('arqel-theme', 'dark');

    expect(computeInitialResolved(opts)).toBe('dark');
  });

  it('falls back to prefers-color-scheme for system with nothing stored', () => {
    mockMatchMedia(true);

    expect(computeInitialResolved({ ...opts, defaultTheme: 'system' })).toBe('dark');
  });

  it('resolves to light when nothing is applied/stored and system prefers light', () => {
    expect(computeInitialResolved({ ...opts, defaultTheme: 'system' })).toBe('light');
  });
});

import { act, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { ThemeProvider, useTheme } from '../src/providers/ThemeProvider.js';

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

describe('ThemeProvider', () => {
  beforeEach(() => {
    window.localStorage.clear();
    document.documentElement.classList.remove('light', 'dark');
  });

  afterEach(() => {
    window.localStorage.clear();
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
});

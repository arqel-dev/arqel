import {
  ThemeProvider as ReactThemeProvider,
  useTheme as useReactTheme,
} from '@arqel-dev/react/providers';
import { act, cleanup, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { ThemeProvider, ThemeToggle, useTheme } from '../index';

import { installMatchMedia } from './matchMedia.helper';

/**
 * Regression tests for issue #236: `@arqel-dev/theme` and
 * `@arqel-dev/react/providers` must share ONE ThemeContext. A toggle
 * exported by either entry point and a consumer of the other entry
 * point's `useTheme` must operate on the same state.
 *
 * (No jest-dom in this package — assert via `.textContent`.)
 */

function ReactConsumer() {
  const { resolved, theme } = useReactTheme();
  return (
    <div>
      <span data-testid="react-resolved">{resolved}</span>
      <span data-testid="react-theme">{theme}</span>
    </div>
  );
}

describe('unified theme context (#236)', () => {
  beforeEach(() => {
    window.localStorage.clear();
    document.documentElement.classList.remove('light', 'dark');
  });

  afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    window.localStorage.clear();
  });

  it('theme ThemeProvider drives react/providers useTheme (one context)', () => {
    installMatchMedia(false);
    render(
      <ThemeProvider defaultTheme="light">
        <ReactConsumer />
      </ThemeProvider>,
    );

    // No throw → same context. Light by default.
    expect(screen.getByTestId('react-resolved').textContent).toBe('light');
    expect(screen.getByTestId('react-theme').textContent).toBe('light');
  });

  it('theme ThemeToggle re-renders a react/providers consumer (shared state)', () => {
    installMatchMedia(false);
    render(
      <ThemeProvider defaultTheme="light">
        <ThemeToggle />
        <ReactConsumer />
      </ThemeProvider>,
    );

    expect(screen.getByTestId('react-theme').textContent).toBe('light');

    // ThemeToggle cycles system → light → dark; from `light` next is `dark`.
    act(() => {
      screen.getByRole('button').click();
    });

    expect(screen.getByTestId('react-theme').textContent).toBe('dark');
    expect(screen.getByTestId('react-resolved').textContent).toBe('dark');
  });

  it("react ThemeProvider drives theme's useTheme (one context)", () => {
    installMatchMedia(false);

    function ThemeConsumer() {
      const { theme, resolvedTheme } = useTheme();
      return (
        <div>
          <span data-testid="theme-theme">{theme}</span>
          <span data-testid="theme-resolved">{resolvedTheme}</span>
        </div>
      );
    }

    render(
      <ReactThemeProvider defaultTheme="dark">
        <ThemeConsumer />
      </ReactThemeProvider>,
    );

    expect(screen.getByTestId('theme-theme').textContent).toBe('dark');
    expect(screen.getByTestId('theme-resolved').textContent).toBe('dark');
  });

  it('both entry points expose the same useTheme reference', () => {
    expect(useTheme).toBe(useReactTheme);
  });

  it('theme ThemeProvider exposes both resolved and resolvedTheme', () => {
    installMatchMedia(false);

    function BothShapes() {
      const ctx = useTheme();
      return (
        <div>
          <span data-testid="shape-resolved">{ctx.resolved}</span>
          <span data-testid="shape-resolvedTheme">{ctx.resolvedTheme}</span>
        </div>
      );
    }

    render(
      <ThemeProvider defaultTheme="dark">
        <BothShapes />
      </ThemeProvider>,
    );

    expect(screen.getByTestId('shape-resolved').textContent).toBe('dark');
    expect(screen.getByTestId('shape-resolvedTheme').textContent).toBe('dark');
  });
});

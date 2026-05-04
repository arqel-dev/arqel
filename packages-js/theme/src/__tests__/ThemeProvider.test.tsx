import { act, cleanup, render, renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { ThemeProvider } from '../ThemeProvider';
import { useTheme } from '../useTheme';

import { installMatchMedia } from './matchMedia.helper';

describe('ThemeProvider', () => {
  beforeEach(() => {
    window.localStorage.clear();
    document.documentElement.classList.remove('dark');
    document.documentElement.removeAttribute('data-theme');
    document.documentElement.style.colorScheme = '';
  });

  afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
  });

  it('aplica classe .dark no <html> quando theme dark', () => {
    installMatchMedia(false);
    function Wrap({ children }: { children: ReactNode }): ReactNode {
      return <ThemeProvider defaultTheme="dark">{children}</ThemeProvider>;
    }
    renderHook(() => useTheme(), { wrapper: Wrap });
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.documentElement.style.colorScheme).toBe('dark');
  });

  it('remove classe .dark quando muda para light', () => {
    installMatchMedia(false);
    function Wrap({ children }: { children: ReactNode }): ReactNode {
      return <ThemeProvider defaultTheme="dark">{children}</ThemeProvider>;
    }
    const { result } = renderHook(() => useTheme(), { wrapper: Wrap });
    expect(document.documentElement.classList.contains('dark')).toBe(true);

    act(() => result.current.setTheme('light'));
    expect(document.documentElement.classList.contains('dark')).toBe(false);
    expect(document.documentElement.style.colorScheme).toBe('light');
  });

  it('reage a matchMedia change quando theme=system', () => {
    const media = installMatchMedia(false);
    function Wrap({ children }: { children: ReactNode }): ReactNode {
      return <ThemeProvider defaultTheme="system">{children}</ThemeProvider>;
    }
    renderHook(() => useTheme(), { wrapper: Wrap });
    expect(document.documentElement.classList.contains('dark')).toBe(false);

    act(() => media.setMatches(true));
    expect(document.documentElement.classList.contains('dark')).toBe(true);
  });

  it('cleanup remove listener de matchMedia', () => {
    const media = installMatchMedia(false);
    const { unmount } = render(
      <ThemeProvider defaultTheme="system">
        <div>x</div>
      </ThemeProvider>,
    );
    expect(media.listeners.length).toBeGreaterThan(0);
    unmount();
    expect(media.listeners.length).toBe(0);
  });

  it('attribute=data-theme define atributo em vez de classe', () => {
    installMatchMedia(false);
    function Wrap({ children }: { children: ReactNode }): ReactNode {
      return (
        <ThemeProvider defaultTheme="dark" attribute="data-theme">
          {children}
        </ThemeProvider>
      );
    }
    renderHook(() => useTheme(), { wrapper: Wrap });
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    expect(document.documentElement.classList.contains('dark')).toBe(false);
  });
});

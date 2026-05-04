import { act, cleanup, render, renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { DEFAULT_STORAGE_KEY } from '../storage';
import { ThemeProvider } from '../ThemeProvider';
import { useTheme } from '../useTheme';

import { installMatchMedia } from './matchMedia.helper';

function wrapper({ children }: { children: ReactNode }): ReactNode {
  return <ThemeProvider>{children}</ThemeProvider>;
}

describe('useTheme', () => {
  beforeEach(() => {
    window.localStorage.clear();
    document.documentElement.classList.remove('dark');
  });

  afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
  });

  it('default theme é "system" quando nada armazenado', () => {
    installMatchMedia(false);
    const { result } = renderHook(() => useTheme(), { wrapper });
    expect(result.current.theme).toBe('system');
    expect(result.current.resolvedTheme).toBe('light');
  });

  it('setTheme persiste em localStorage', () => {
    installMatchMedia(false);
    const { result } = renderHook(() => useTheme(), { wrapper });
    act(() => {
      result.current.setTheme('dark');
    });
    expect(result.current.theme).toBe('dark');
    expect(result.current.resolvedTheme).toBe('dark');
    expect(window.localStorage.getItem(DEFAULT_STORAGE_KEY)).toBe('dark');
  });

  it('resolvedTheme reage a matchMedia change quando theme=system', () => {
    const media = installMatchMedia(false);
    const { result } = renderHook(() => useTheme(), { wrapper });
    expect(result.current.resolvedTheme).toBe('light');

    act(() => {
      media.setMatches(true);
    });
    expect(result.current.resolvedTheme).toBe('dark');
  });

  it('lê preferência prévia do localStorage', () => {
    window.localStorage.setItem(DEFAULT_STORAGE_KEY, 'dark');
    installMatchMedia(false);
    const { result } = renderHook(() => useTheme(), { wrapper });
    expect(result.current.theme).toBe('dark');
    expect(result.current.resolvedTheme).toBe('dark');
  });

  it('lança erro se chamado fora de ThemeProvider', () => {
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});
    function Comp(): ReactNode {
      useTheme();
      return null;
    }
    expect(() => render(<Comp />)).toThrow(/ThemeProvider/);
    spy.mockRestore();
  });
});

import { createContext, type ReactNode, useCallback, useEffect, useMemo, useState } from 'react';

import { DEFAULT_STORAGE_KEY, getSystemTheme, readStoredTheme, writeStoredTheme } from './storage';
import type { ResolvedTheme, Theme, ThemeContextValue } from './types';

export const ThemeContext = createContext<ThemeContextValue | null>(null);

export interface ThemeProviderProps {
  children: ReactNode;
  /** Tema padrão quando nada está armazenado. Default: `system`. */
  defaultTheme?: Theme;
  /** Chave usada em localStorage. Default: `arqel-theme`. */
  storageKey?: string;
  /** Classe aplicada no `<html>` quando dark. Default: `dark`. */
  darkClass?: string;
  /** Atributo opcional usado em vez de classe (`data-theme`). Útil para integrações. */
  attribute?: 'class' | 'data-theme';
}

function resolve(theme: Theme): ResolvedTheme {
  return theme === 'system' ? getSystemTheme() : theme;
}

function applyToDom(
  resolved: ResolvedTheme,
  darkClass: string,
  attribute: 'class' | 'data-theme',
): void {
  if (typeof document === 'undefined') return;
  const root = document.documentElement;
  if (attribute === 'class') {
    if (resolved === 'dark') root.classList.add(darkClass);
    else root.classList.remove(darkClass);
  } else {
    root.setAttribute('data-theme', resolved);
  }
  // colorScheme ajuda nativos (scrollbars, form controls)
  root.style.colorScheme = resolved;
}

export function ThemeProvider({
  children,
  defaultTheme = 'system',
  storageKey = DEFAULT_STORAGE_KEY,
  darkClass = 'dark',
  attribute = 'class',
}: ThemeProviderProps): ReactNode {
  // Inicialização lazy — lê localStorage 1x.
  const [theme, setThemeState] = useState<Theme>(() => readStoredTheme(storageKey) ?? defaultTheme);
  const [resolvedTheme, setResolvedTheme] = useState<ResolvedTheme>(() => resolve(theme));

  // Aplica no DOM e mantém em sync com matchMedia se theme === 'system'.
  useEffect(() => {
    const next = resolve(theme);
    setResolvedTheme(next);
    applyToDom(next, darkClass, attribute);

    if (
      theme !== 'system' ||
      typeof window === 'undefined' ||
      typeof window.matchMedia !== 'function'
    ) {
      return;
    }

    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const onChange = (): void => {
      const r: ResolvedTheme = media.matches ? 'dark' : 'light';
      setResolvedTheme(r);
      applyToDom(r, darkClass, attribute);
    };
    media.addEventListener('change', onChange);
    return () => {
      media.removeEventListener('change', onChange);
    };
  }, [theme, darkClass, attribute]);

  const setTheme = useCallback(
    (t: Theme) => {
      setThemeState(t);
      writeStoredTheme(t, storageKey);
    },
    [storageKey],
  );

  const value = useMemo<ThemeContextValue>(
    () => ({ theme, resolvedTheme, setTheme }),
    [theme, resolvedTheme, setTheme],
  );

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

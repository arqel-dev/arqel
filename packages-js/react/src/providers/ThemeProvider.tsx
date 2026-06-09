import {
  createContext,
  type ReactNode,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';

export type Theme = 'light' | 'dark' | 'system';

export type ResolvedTheme = 'light' | 'dark';

export interface ThemeContextValue {
  theme: Theme;
  /** Concrete theme applied to `<html>` (always `light` or `dark`). */
  resolved: ResolvedTheme;
  /**
   * Alias of {@link resolved}. Kept for backward-compat with the
   * `@arqel-dev/theme` value shape (`resolvedTheme`), which now shares
   * this single context (issue #236).
   */
  resolvedTheme: ResolvedTheme;
  setTheme: (theme: Theme) => void;
  toggle: () => void;
}

const STORAGE_KEY = 'arqel-theme';

const ThemeContext = createContext<ThemeContextValue | null>(null);

ThemeContext.displayName = 'ThemeContext';

export { ThemeContext };

interface ThemeProviderProps {
  defaultTheme?: Theme;
  storageKey?: string;
  /** Class applied to `<html>` when dark. Default: `dark`. */
  darkClass?: string;
  /** Use a `data-theme` attribute instead of a class. Default: `class`. */
  attribute?: 'class' | 'data-theme';
  children: ReactNode;
}

/**
 * Manages light/dark/system theme with localStorage persistence.
 *
 * SSR-safe: on the server we render with `defaultTheme` and only
 * read localStorage after mount. The `app.blade.php` FOUC guard
 * (CORE-012) applies the persisted theme class before React
 * hydrates, so users never see a wrong-theme flash.
 */
export function ThemeProvider({
  defaultTheme = 'system',
  storageKey = STORAGE_KEY,
  darkClass = 'dark',
  attribute = 'class',
  children,
}: ThemeProviderProps): ReactNode {
  const [theme, setThemeState] = useState<Theme>(defaultTheme);
  const [resolved, setResolved] = useState<ResolvedTheme>('light');

  // Hydrate from localStorage after mount.
  useEffect(() => {
    if (typeof window === 'undefined') return;

    const stored = window.localStorage.getItem(storageKey) as Theme | null;
    if (stored === 'light' || stored === 'dark' || stored === 'system') {
      setThemeState(stored);
    }
  }, [storageKey]);

  // Resolve `system` → light/dark via prefers-color-scheme.
  useEffect(() => {
    if (typeof window === 'undefined') return;

    const apply = (next: ResolvedTheme): void => {
      setResolved(next);
      const root = window.document.documentElement;
      if (attribute === 'data-theme') {
        root.setAttribute('data-theme', next);
      } else {
        root.classList.remove(darkClass);
        if (next === 'dark') root.classList.add(darkClass);
      }
      root.style.colorScheme = next;
    };

    if (theme !== 'system') {
      apply(theme);
      return;
    }

    const media = window.matchMedia('(prefers-color-scheme: dark)');
    apply(media.matches ? 'dark' : 'light');

    // Re-read from the media query (not the event arg) so the handler is
    // robust to dispatchers that fire listeners without a MediaQueryListEvent.
    const listener = (): void => {
      apply(media.matches ? 'dark' : 'light');
    };

    media.addEventListener('change', listener);
    return () => media.removeEventListener('change', listener);
  }, [theme, darkClass, attribute]);

  const setTheme = useCallback(
    (next: Theme) => {
      setThemeState(next);
      if (typeof window !== 'undefined') {
        window.localStorage.setItem(storageKey, next);
      }
    },
    [storageKey],
  );

  const toggle = useCallback(() => {
    setTheme(resolved === 'dark' ? 'light' : 'dark');
  }, [resolved, setTheme]);

  const value = useMemo<ThemeContextValue>(
    () => ({ theme, resolved, resolvedTheme: resolved, setTheme, toggle }),
    [theme, resolved, setTheme, toggle],
  );

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useTheme(): ThemeContextValue {
  const context = useContext(ThemeContext);

  if (context === null) {
    throw new Error('useTheme(): no ThemeProvider in the tree.');
  }

  return context;
}

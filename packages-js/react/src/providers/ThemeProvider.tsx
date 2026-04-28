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
  resolved: ResolvedTheme;
  setTheme: (theme: Theme) => void;
  toggle: () => void;
}

const STORAGE_KEY = 'arqel-theme';

const ThemeContext = createContext<ThemeContextValue | null>(null);

ThemeContext.displayName = 'ThemeContext';

interface ThemeProviderProps {
  defaultTheme?: Theme;
  storageKey?: string;
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
      root.classList.remove('light', 'dark');
      root.classList.add(next);
    };

    if (theme !== 'system') {
      apply(theme);
      return;
    }

    const media = window.matchMedia('(prefers-color-scheme: dark)');
    apply(media.matches ? 'dark' : 'light');

    const listener = (event: MediaQueryListEvent): void => {
      apply(event.matches ? 'dark' : 'light');
    };

    media.addEventListener('change', listener);
    return () => media.removeEventListener('change', listener);
  }, [theme]);

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
    () => ({ theme, resolved, setTheme, toggle }),
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

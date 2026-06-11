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

interface ResolveInitOptions {
  storageKey: string;
  darkClass: string;
  attribute: 'class' | 'data-theme';
  defaultTheme: Theme;
}

/**
 * Computes the initial `resolved` value so the first paint matches what
 * the FOUC guard ({@link preventFlashScript}) already applied to `<html>`.
 *
 * On the client it reads the truth already on the page — the `darkClass`
 * on `<html>` (or the `data-theme` attribute) — falling back to
 * `localStorage` + `prefers-color-scheme` (mirroring the FOUC snippet).
 * On the server (`document` undefined) it resolves `defaultTheme`.
 */
/**
 * Exported for direct unit testing of the first-paint resolution (issue
 * #247) in isolation from the post-mount effect. Not re-exported from the
 * package barrel — internal to the provider.
 */
export function computeInitialResolved({
  storageKey,
  darkClass,
  attribute,
  defaultTheme,
}: ResolveInitOptions): ResolvedTheme {
  if (typeof document === 'undefined') {
    // SSR: no DOM/storage to read; resolve the default (system → light).
    return defaultTheme === 'dark' ? 'dark' : 'light';
  }

  // Prefer the state the FOUC script already applied to <html>.
  const root = document.documentElement;
  if (attribute === 'data-theme') {
    const applied = root.getAttribute('data-theme');
    if (applied === 'dark' || applied === 'light') return applied;
  } else if (root.classList.contains(darkClass)) {
    return 'dark';
  }

  // Fall back to the FOUC snippet's own logic: stored pref + system query.
  let stored: string | null = null;
  try {
    stored = window.localStorage.getItem(storageKey);
  } catch {
    stored = null;
  }

  if (stored === 'dark' || stored === 'light') return stored;

  // `system` (or no stored pref) → resolve via prefers-color-scheme, like
  // the FOUC snippet. A non-`system` defaultTheme wins when nothing is stored.
  const pref = stored === 'system' ? 'system' : defaultTheme;
  if (pref === 'dark') return 'dark';
  if (pref === 'light') return 'light';

  const prefersDark =
    typeof window.matchMedia === 'function' &&
    window.matchMedia('(prefers-color-scheme: dark)').matches;
  return prefersDark ? 'dark' : 'light';
}

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
  // Lazy-init `resolved` from the state the FOUC guard already applied to
  // `<html>` so the first paint (and SSR) toggle icon/aria-label are correct
  // instead of a hardcoded 'light' (issue #247).
  const [resolved, setResolved] = useState<ResolvedTheme>(() =>
    computeInitialResolved({ storageKey, darkClass, attribute, defaultTheme }),
  );

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

    // `matchMedia` is absent in some environments (older jsdom/happy-dom, a
    // non-browser host). Without it, resolve `system` once to light and skip
    // the listener instead of throwing.
    if (typeof window.matchMedia !== 'function') {
      apply('light');
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

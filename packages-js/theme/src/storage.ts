import type { Theme } from './types';

export const DEFAULT_STORAGE_KEY = 'arqel-theme';

const VALID_THEMES: readonly Theme[] = ['light', 'dark', 'system'];

export function isTheme(value: unknown): value is Theme {
  return typeof value === 'string' && (VALID_THEMES as readonly string[]).includes(value);
}

/**
 * Lê preferência do localStorage. SSR-safe: retorna `null` no servidor.
 */
export function readStoredTheme(key: string = DEFAULT_STORAGE_KEY): Theme | null {
  if (typeof window === 'undefined') return null;
  try {
    const raw = window.localStorage.getItem(key);
    return isTheme(raw) ? raw : null;
  } catch {
    // localStorage pode estar bloqueado (Safari private, iframes, etc).
    return null;
  }
}

/**
 * Persiste preferência em localStorage. Silencioso em erro.
 */
export function writeStoredTheme(theme: Theme, key: string = DEFAULT_STORAGE_KEY): void {
  if (typeof window === 'undefined') return;
  try {
    window.localStorage.setItem(key, theme);
  } catch {
    // ignore
  }
}

/**
 * Detecta system preference. SSR-safe: retorna `light` no servidor.
 */
export function getSystemTheme(): 'light' | 'dark' {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return 'light';
  }
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

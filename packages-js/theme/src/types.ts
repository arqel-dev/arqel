/**
 * Theme preference armazenada em localStorage / Context.
 *
 * - `light` — força modo claro
 * - `dark` — força modo escuro
 * - `system` — segue `prefers-color-scheme` do SO
 */
export type Theme = 'light' | 'dark' | 'system';

/**
 * Tema efetivamente aplicado ao DOM (já resolvido a partir de `Theme`).
 */
export type ResolvedTheme = 'light' | 'dark';

export interface ThemeContextValue {
  /** Preferência do utilizador (incluindo `system`). */
  theme: Theme;
  /** Tema concreto aplicado no `<html>` (sempre `light` ou `dark`). */
  resolvedTheme: ResolvedTheme;
  /** Atualiza preferência (persiste em localStorage). */
  setTheme: (theme: Theme) => void;
}

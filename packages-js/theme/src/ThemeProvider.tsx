/**
 * Issue #236: `@arqel-dev/theme` and `@arqel-dev/react/providers` used
 * to expose two *separate* React contexts, so a `ThemeToggle` from this
 * package was a no-op inside any `@arqel-dev/ui` shell (whose Topbar
 * reads the react/providers context).
 *
 * This module now re-exports the single source of truth from
 * `@arqel-dev/react/providers`. There is exactly ONE `ThemeContext`:
 * toggling here re-renders the shell, and vice-versa.
 *
 * The richer props (`darkClass`/`attribute`/`storageKey`) live on the
 * react provider — see {@link ThemeProviderProps}.
 */
export {
  ThemeContext,
  ThemeProvider,
} from '@arqel-dev/react/providers';

export interface ThemeProviderProps {
  children: import('react').ReactNode;
  /** Tema padrão quando nada está armazenado. Default: `system`. */
  defaultTheme?: import('./types').Theme;
  /** Chave usada em localStorage. Default: `arqel-theme`. */
  storageKey?: string;
  /** Classe aplicada no `<html>` quando dark. Default: `dark`. */
  darkClass?: string;
  /** Atributo opcional usado em vez de classe (`data-theme`). */
  attribute?: 'class' | 'data-theme';
}

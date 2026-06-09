/**
 * Theme types — re-exported from the single source of truth
 * (`@arqel-dev/react/providers`, issue #236).
 *
 * - `light` — força modo claro
 * - `dark` — força modo escuro
 * - `system` — segue `prefers-color-scheme` do SO
 *
 * `ThemeContextValue` carries both `resolved` (react/providers shape)
 * and `resolvedTheme` (the historical `@arqel-dev/theme` alias) so
 * existing consumers of either shape keep working.
 */
export type {
  ResolvedTheme,
  Theme,
  ThemeContextValue,
} from '@arqel-dev/react/providers';

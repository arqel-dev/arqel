import { useContext } from 'react';

import { ThemeContext } from './ThemeProvider';
import type { ThemeContextValue } from './types';

/**
 * Hook para acessar o tema atual e atualizá-lo.
 *
 * Lança erro se chamado fora de `<ThemeProvider>` (fail-fast — evita
 * componentes silenciosamente mostrando defaults errados).
 */
export function useTheme(): ThemeContextValue {
  const ctx = useContext(ThemeContext);
  if (!ctx) {
    throw new Error('[arqel/theme] useTheme() deve ser usado dentro de <ThemeProvider>.');
  }
  return ctx;
}

import type { PanelPayload } from '@arqel/types/inertia';
import type { ReactNode } from 'react';

import { PanelContext } from '../context/PanelContext.js';
import { TenantContext } from '../context/TenantContext.js';
import { type Theme, ThemeProvider } from './ThemeProvider.js';

export interface ArqelProviderProps {
  /**
   * Initial panel payload — typically read from
   * `usePage<SharedProps>().props.panel` and forwarded by
   * `createArqelApp`.
   */
  panel?: PanelPayload | null;
  /** Tenant payload (Phase 2 — kept null in Phase 1). */
  tenant?: unknown;
  /** Default theme; user preferences persist in localStorage. */
  defaultTheme?: Theme;
  children: ReactNode;
}

/**
 * Top-level provider that wires the panel/tenant contexts together
 * with the theme manager. Apps wrap their root once in
 * `createArqelApp`; you only render `<ArqelProvider>` directly when
 * mounting Arqel components outside an Inertia entrypoint (e.g.
 * Storybook stories or unit tests).
 */
export function ArqelProvider({
  panel = null,
  tenant = null,
  defaultTheme = 'system',
  children,
}: ArqelProviderProps): ReactNode {
  return (
    <PanelContext.Provider value={panel}>
      <TenantContext.Provider value={tenant}>
        <ThemeProvider defaultTheme={defaultTheme}>{children}</ThemeProvider>
      </TenantContext.Provider>
    </PanelContext.Provider>
  );
}

import type { PanelPayload } from '@arqel-dev/types/inertia';
import { createContext, useContext } from 'react';

/**
 * Panel context — populated by `ArqelProvider` from the Inertia
 * shared props. Null-by-default so descendants can detect the
 * absence of a panel (e.g. running outside a registered panel
 * scope) without throwing.
 */
export const PanelContext = createContext<PanelPayload | null>(null);

PanelContext.displayName = 'PanelContext';

export function usePanel(): PanelPayload | null {
  return useContext(PanelContext);
}

export function useRequiredPanel(): PanelPayload {
  const panel = useContext(PanelContext);

  if (panel === null) {
    throw new Error(
      'useRequiredPanel(): no PanelContext value. Wrap your tree with <ArqelProvider> or <PanelContext.Provider>.',
    );
  }

  return panel;
}

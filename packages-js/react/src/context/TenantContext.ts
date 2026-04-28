import { createContext, useContext } from 'react';

/**
 * Tenant context. Phase 1 stub — always `null`. Phase 2 expands to
 * a real `Tenant` shape with switchers, scoped resources, etc.
 *
 * The context exists in Phase 1 so consumer code can read
 * `useTenant()` without adapting later.
 */
export const TenantContext = createContext<unknown>(null);

TenantContext.displayName = 'TenantContext';

export function useTenant(): unknown {
  return useContext(TenantContext);
}

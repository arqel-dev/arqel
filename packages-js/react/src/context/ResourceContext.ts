import type { ResourceMeta } from '@arqel-dev/types/resources';
import { createContext, useContext } from 'react';

/**
 * Resource context — set by `<ResourceLayout>` (Phase 1: layout
 * components in @arqel-dev/ui set this; the bare provider lives here
 * so hooks can read it).
 */
export const ResourceContext = createContext<ResourceMeta | null>(null);

ResourceContext.displayName = 'ResourceContext';

export function useResourceContext(): ResourceMeta | null {
  return useContext(ResourceContext);
}

export function useRequiredResource(): ResourceMeta {
  const resource = useContext(ResourceContext);

  if (resource === null) {
    throw new Error(
      'useRequiredResource(): no ResourceContext value. Make sure the page is wrapped with <ResourceLayout> or <ResourceContext.Provider>.',
    );
  }

  return resource;
}

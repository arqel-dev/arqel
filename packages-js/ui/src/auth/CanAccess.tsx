/**
 * `<CanAccess>` — conditional render based on UX-only ability check.
 *
 * Real enforcement happens server-side (ADR-017); this component just
 * skips children when the current user lacks the ability so we can
 * keep markup declarative.
 */

import { useCanAccess } from '@arqel-dev/hooks';
import type { ReactNode } from 'react';

export interface CanAccessProps {
  ability: string;
  record?: unknown;
  children: ReactNode;
  fallback?: ReactNode;
}

export function CanAccess({ ability, record, children, fallback = null }: CanAccessProps) {
  const allowed = useCanAccess(ability, record);
  return <>{allowed ? children : fallback}</>;
}

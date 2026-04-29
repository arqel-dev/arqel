/**
 * `useCanAccess` — UX-only ability check.
 *
 * Reads `auth.can[ability]` from Inertia shared props. When a record is
 * supplied and the record itself exposes a `can: Record<string, boolean>`
 * map (per-record abilities serialised server-side), that wins over the
 * global map. Real enforcement happens server-side via Policies (ADR-017).
 */

import type { SharedProps } from '@arqel/types/inertia';
import { usePage } from '@inertiajs/react';

interface RecordWithAbilities {
  can?: Record<string, boolean>;
}

export function useCanAccess(ability: string, record?: unknown): boolean {
  const page = usePage();
  const props = page.props as unknown as SharedProps;
  const recordCan = (record as RecordWithAbilities | null | undefined)?.can;

  if (recordCan && Object.hasOwn(recordCan, ability)) {
    return recordCan[ability] === true;
  }

  const globalCan = props.auth?.can ?? {};
  return Object.hasOwn(globalCan, ability) ? globalCan[ability] === true : false;
}

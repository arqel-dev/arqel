/**
 * `useResource` — typed access to Inertia page props for a Resource page.
 *
 * Combines `usePage()` (shared props) with the optional ResourceContext
 * provided by `<ResourceLayout>` so callsites can pull `records`, `actions`,
 * `filters`, etc. with a single hook.
 */

import { useResourceContext } from '@arqel-dev/react/context';
import type { ResourceMeta } from '@arqel-dev/types/resources';
import { usePage } from '@inertiajs/react';

export interface UseResourceResult<TRecord = unknown> {
  /** Server-rendered Resource metadata (label, slug, fields, etc.). */
  resource: ResourceMeta | null;
  /** Records collection — `null` on non-index pages. */
  records: TRecord[] | null;
  /** Single record — `null` on index pages. */
  record: TRecord | null;
  /** Active filters as serialised by Table::serialize(). */
  filters: Record<string, unknown>;
  /** Raw page props for escape-hatch use. */
  props: Record<string, unknown>;
}

interface ResourcePagePayload<TRecord> {
  resource?: ResourceMeta;
  records?: TRecord[];
  record?: TRecord;
  filters?: Record<string, unknown>;
}

export function useResource<TRecord = unknown>(): UseResourceResult<TRecord> {
  const page = usePage();
  const props = page.props as Record<string, unknown> & ResourcePagePayload<TRecord>;
  const contextResource = useResourceContext();

  return {
    resource: props.resource ?? contextResource ?? null,
    records: Array.isArray(props.records) ? (props.records as TRecord[]) : null,
    record: (props.record as TRecord | undefined) ?? null,
    filters: (props.filters as Record<string, unknown> | undefined) ?? {},
    props,
  };
}

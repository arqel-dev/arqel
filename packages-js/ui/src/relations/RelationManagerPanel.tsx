/**
 * `<RelationManagerPanel>` — one relation-manager tab on a parent's edit
 * page: a `DataTable` of related records with ability-gated toolbar (New /
 * Attach) and per-row Edit actions. Mutation (create, attach, edit) is
 * still delegated to the parent page via the callbacks; this component
 * owns no Inertia visits itself.
 *
 * `relation.table` is the PHP `Table::toArray()` payload (untyped on the
 * wire per `RelationManagerProps`'s docblock); the column list lives at
 * `relation.table.columns`, mirroring how `ArqelIndexPage`/`ResourceIndex`
 * feed `DataTable` from `ResourceIndexProps.columns`.
 *
 * **Records loading**: `RelationManager::toArray()` deliberately does NOT
 * include the related records (see `RelationManagerProps`'s docblock) — the
 * actual rows live behind `RelationController::index()`
 * (`GET {basePath}/{parentSlug}/{parentId}/relations/{slug}`), a plain JSON
 * endpoint (`{ records, table, abilities }`, not an Inertia partial). This
 * panel is therefore self-fetching: it mirrors `WidgetRenderer`'s
 * `useEffect` + native `fetch()` pattern (see that component's docblock) —
 * fetch on mount, `Accept: application/json`, swallow failures so a bad
 * request just leaves the previous records on screen instead of crashing
 * the tab. The `records` prop is kept as the *initial* seed (SSR-friendly,
 * and it's what the existing unit tests assert against) — the fetch result
 * replaces it once it resolves. `refreshKey` lets a caller force a refetch
 * (e.g. after a successful create/edit/attach/detach mutation) by bumping a
 * counter/string, since those mutations no longer flow through an Inertia
 * partial reload of a `relations` prop.
 */

import type { RelationManagerProps } from '@arqel-dev/types/relations';
import type { ColumnSchema } from '@arqel-dev/types/tables';
import { useEffect, useState } from 'react';
import { Button } from '../action/Button.js';
import { DataTable, type DataTableRecord } from '../table/DataTable.js';
import { ErrorState } from '../utility/ErrorState.js';

export interface RelationManagerPanelProps {
  relation: RelationManagerProps;
  parentSlug: string;
  parentId: string | number;
  records: unknown[];
  basePath?: string;
  /** Bump this (e.g. increment a counter) to force a refetch after a mutation. */
  refreshKey?: string | number;
  /** Test/DI seam — defaults to the native `fetch` API, mirroring `WidgetRenderer`. */
  fetcher?: ((url: string) => Promise<unknown>) | undefined;
  onEdit(id: string | number): void;
  onCreate(): void;
  onAttach(): void;
}

function relationColumns(table: unknown): ColumnSchema[] {
  if (
    table &&
    typeof table === 'object' &&
    Array.isArray((table as { columns?: unknown }).columns)
  ) {
    return (table as { columns: ColumnSchema[] }).columns;
  }
  return [];
}

export function RelationManagerPanel({
  relation,
  parentSlug,
  parentId,
  records: initialRecords,
  basePath = '/admin',
  refreshKey,
  fetcher,
  onEdit,
  onCreate,
  onAttach,
}: RelationManagerPanelProps) {
  const { abilities, type, slug } = relation;
  const columns = relationColumns(relation.table);

  const [records, setRecords] = useState<unknown[]>(initialRecords);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(false);

  // biome-ignore lint/correctness/useExhaustiveDependencies: refreshKey is a deliberate re-fetch trigger (bumped by the caller post-mutation), not a value the effect reads.
  useEffect(() => {
    let cancelled = false;
    const url = `${basePath}/${parentSlug}/${parentId}/relations/${slug}`;

    const doFetch = async () => {
      setLoading(true);
      try {
        const body = fetcher
          ? await fetcher(url)
          : await fetch(url, { headers: { Accept: 'application/json' } }).then((r) => r.json());
        const fetched =
          body && typeof body === 'object' && Array.isArray((body as { records?: unknown }).records)
            ? (body as { records: unknown[] }).records
            : undefined;
        if (!cancelled && fetched) {
          setRecords(fetched);
          setError(false);
        }
      } catch {
        // Swallow — the previous records (initial seed or last successful
        // fetch) stay on screen rather than blanking the tab.
        if (!cancelled) setError(true);
      } finally {
        if (!cancelled) setLoading(false);
      }
    };

    void doFetch();

    return () => {
      cancelled = true;
    };
  }, [basePath, parentSlug, parentId, slug, refreshKey, fetcher]);

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-end gap-2">
        {abilities.create && (
          <Button type="button" size="sm" onClick={onCreate}>
            New
          </Button>
        )}
        {type === 'belongsToMany' && abilities.attach && (
          <Button type="button" size="sm" variant="secondary" onClick={onAttach}>
            Attach
          </Button>
        )}
      </div>
      {error && records.length === 0 ? (
        <ErrorState title="Couldn't load records" description="Please try again." />
      ) : (
        <DataTable<DataTableRecord>
          columns={columns}
          records={records as DataTableRecord[]}
          loading={loading}
          rowActions={
            abilities.update
              ? (record) => (
                  <Button type="button" size="sm" variant="ghost" onClick={() => onEdit(record.id)}>
                    Edit
                  </Button>
                )
              : undefined
          }
        />
      )}
    </div>
  );
}

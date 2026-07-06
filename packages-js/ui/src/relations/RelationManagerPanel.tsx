/**
 * `<RelationManagerPanel>` — one relation-manager tab on a parent's edit
 * page: a `DataTable` of related records with ability-gated toolbar (New /
 * Attach) and per-row Edit/Delete/Detach actions. Create/attach/edit
 * mutations are delegated to the parent page via the callbacks; Delete and
 * Detach are owned by this component directly (Task 13c) since they need
 * no form — just a confirmation and an Inertia `router.delete()` call,
 * mirroring how `ArqelIndexPage`'s row actions confirm via `ConfirmDialog`
 * before dispatching.
 *
 * Delete targets `hasMany`/`morphMany` relations (`abilities.delete`);
 * Detach targets `belongsToMany` (`abilities.detach`) and only removes the
 * pivot row — the related record survives (guaranteed server-side by
 * `RelationController::detach()`). The two are mutually exclusive by
 * `relation.type`, matching the existing New/Attach toolbar gating.
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
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '../action/Button.js';
import { ConfirmDialog } from '../action/ConfirmDialog.js';
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
  /** Called after a Delete or Detach `router.delete()` succeeds, so the caller can bump `refreshKey`. */
  onMutated?: () => void;
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
  onMutated,
}: RelationManagerPanelProps) {
  const { abilities, type, slug } = relation;
  const columns = relationColumns(relation.table);

  const [records, setRecords] = useState<unknown[]>(initialRecords);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(false);
  const [pendingAction, setPendingAction] = useState<{
    kind: 'delete' | 'detach';
    id: string | number;
  } | null>(null);
  const [mutating, setMutating] = useState(false);

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

  const canDelete = type !== 'belongsToMany' && abilities.delete;
  const canDetach = type === 'belongsToMany' && abilities.detach;

  const mutationUrl = (id: string | number, kind: 'delete' | 'detach'): string => {
    const base = `${basePath}/${parentSlug}/${parentId}/relations/${slug}/${id}`;
    return kind === 'detach' ? `${base}/detach` : base;
  };

  const confirmMutation = () => {
    if (!pendingAction) return;
    const { kind, id } = pendingAction;
    setMutating(true);
    router.delete(mutationUrl(id, kind), {
      preserveScroll: true,
      onSuccess: () => {
        setMutating(false);
        setPendingAction(null);
        onMutated?.();
      },
      onError: () => {
        setMutating(false);
      },
    });
  };

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
            abilities.update || canDelete || canDetach
              ? (record) => (
                  <>
                    {abilities.update && (
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => onEdit(record.id)}
                      >
                        Edit
                      </Button>
                    )}
                    {canDelete && (
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => setPendingAction({ kind: 'delete', id: record.id })}
                      >
                        Delete
                      </Button>
                    )}
                    {canDetach && (
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => setPendingAction({ kind: 'detach', id: record.id })}
                      >
                        Detach
                      </Button>
                    )}
                  </>
                )
              : undefined
          }
        />
      )}
      <ConfirmDialog
        open={pendingAction !== null}
        onOpenChange={(next) => {
          if (!next) setPendingAction(null);
        }}
        onConfirm={confirmMutation}
        processing={mutating}
        config={
          pendingAction?.kind === 'detach'
            ? {
                heading: 'Detach this record?',
                color: 'destructive',
                submitLabel: 'Detach',
              }
            : {
                heading: 'Are you sure?',
                color: 'destructive',
                submitLabel: 'Delete',
              }
        }
      />
    </div>
  );
}

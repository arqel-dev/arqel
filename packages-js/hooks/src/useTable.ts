/**
 * `useTable` — table state (sort, filters, selection).
 *
 * Phase 1 scope: pure local state. URL sync via Inertia `router.get`
 * lands in HOOKS-004 follow-up once `<ResourceIndex>` exists.
 */

import { useCallback, useState } from 'react';

export type SortDirection = 'asc' | 'desc';

export interface TableSort {
  column: string;
  direction: SortDirection;
}

export interface UseTableOptions {
  defaultSort?: TableSort;
  defaultFilters?: Record<string, unknown>;
  defaultSelection?: ReadonlyArray<string | number>;
}

export interface UseTableResult {
  sort: TableSort | null;
  setSort: (column: string, direction?: SortDirection) => void;
  clearSort: () => void;
  filters: Record<string, unknown>;
  setFilter: (name: string, value: unknown) => void;
  clearFilters: () => void;
  selectedIds: ReadonlyArray<string | number>;
  toggleSelection: (id: string | number) => void;
  selectAll: (ids: ReadonlyArray<string | number>) => void;
  clearSelection: () => void;
  isSelected: (id: string | number) => boolean;
}

export function useTable(options: UseTableOptions = {}): UseTableResult {
  const [sort, setSortState] = useState<TableSort | null>(options.defaultSort ?? null);
  const [filters, setFilters] = useState<Record<string, unknown>>(options.defaultFilters ?? {});
  const [selectedIds, setSelectedIds] = useState<ReadonlyArray<string | number>>(
    options.defaultSelection ?? [],
  );

  const setSort = useCallback((column: string, direction: SortDirection = 'asc') => {
    setSortState({ column, direction });
  }, []);

  const clearSort = useCallback(() => setSortState(null), []);

  const setFilter = useCallback((name: string, value: unknown) => {
    setFilters((prev) => {
      if (value === undefined || value === null || value === '') {
        const { [name]: _omit, ...rest } = prev;
        return rest;
      }
      return { ...prev, [name]: value };
    });
  }, []);

  const clearFilters = useCallback(() => setFilters({}), []);

  const toggleSelection = useCallback((id: string | number) => {
    setSelectedIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
  }, []);

  const selectAll = useCallback((ids: ReadonlyArray<string | number>) => {
    setSelectedIds([...ids]);
  }, []);

  const clearSelection = useCallback(() => setSelectedIds([]), []);

  const isSelected = useCallback((id: string | number) => selectedIds.includes(id), [selectedIds]);

  return {
    sort,
    setSort,
    clearSort,
    filters,
    setFilter,
    clearFilters,
    selectedIds,
    toggleSelection,
    selectAll,
    clearSelection,
    isSelected,
  };
}

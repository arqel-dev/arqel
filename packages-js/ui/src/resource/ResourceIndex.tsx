/**
 * `<ResourceIndex>` — page-level wrapper for index/listing routes.
 *
 * Wires `ResourceIndexProps` (server payload) into the table stack:
 *   header (title + toolbar actions + create button)
 *   → TableToolbar (search + filters + bulk bar)
 *   → DataTable (with selection, sorting, row actions)
 *   → TablePagination
 *
 * The component is presentational — selection / sort / page transitions
 * are lifted to props so the same shell can be reused by ResourceIndex
 * (Inertia-driven) and dashboard widgets (memory-driven).
 */

import type { RecordType, ResourceIndexProps as ServerProps } from '@arqel/types/resources';
import type { SortDirection, TableSort } from '@arqel/types/tables';
import type { ReactNode } from 'react';
import { DataTable } from '../table/DataTable.js';
import { TableFilters } from '../table/TableFilters.js';
import { TablePagination } from '../table/TablePagination.js';
import { TableToolbar } from '../table/TableToolbar.js';
import { cn } from '../utils/cn.js';

type RowId = string | number;

export interface ResourceIndexUIProps<TRecord extends RecordType = RecordType>
  extends ServerProps<TRecord> {
  selectedIds?: ReadonlyArray<RowId>;
  onSelectionChange?: (ids: RowId[]) => void;
  onSortChange?: (column: string, direction: SortDirection) => void;
  onPageChange?: (page: number) => void;
  onPerPageChange?: (perPage: number) => void;
  onSearchChange?: (search: string) => void;
  onFilterChange?: (name: string, value: unknown) => void;
  onClearFilters?: () => void;

  filterValues?: Record<string, unknown>;

  searchSlot?: ReactNode;
  toolbarActions?: ReactNode;
  rowActions?: (record: TRecord) => ReactNode;
  bulkActions?: ReactNode;
  emptyState?: ReactNode;

  loading?: boolean;
  className?: string;
}

export function ResourceIndex<TRecord extends RecordType = RecordType>({
  resource,
  records,
  pagination,
  columns,
  filters,
  search,
  sort,
  selectedIds = [],
  filterValues = {},
  onSelectionChange,
  onSortChange,
  onPageChange,
  onPerPageChange,
  onSearchChange,
  onFilterChange,
  onClearFilters,
  searchSlot,
  toolbarActions,
  rowActions,
  bulkActions,
  emptyState,
  loading = false,
  className,
}: ResourceIndexUIProps<TRecord>) {
  const enableSelection = Boolean(onSelectionChange);

  const renderSearch = (): ReactNode => {
    if (searchSlot) return searchSlot;
    if (!onSearchChange) return null;
    return (
      <label className="flex flex-col gap-1 text-xs text-[var(--color-arqel-muted-fg)]">
        Search
        <input
          type="search"
          placeholder={`Search ${resource.pluralLabel.toLowerCase()}…`}
          className="h-9 w-64 rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-input)] bg-[var(--color-arqel-bg)] px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)]"
          value={search ?? ''}
          onChange={(e) => onSearchChange(e.target.value)}
        />
      </label>
    );
  };

  const normalisedSort: TableSort | null = sort?.column
    ? {
        column: sort.column,
        direction: sort.direction === 'desc' ? 'desc' : 'asc',
      }
    : null;

  return (
    <section
      className={cn(
        'flex flex-col rounded-[var(--radius-arqel)] border border-[var(--color-arqel-border)] bg-[var(--color-arqel-bg)]',
        className,
      )}
      aria-label={resource.pluralLabel}
    >
      <header className="flex flex-wrap items-center gap-3 border-b border-[var(--color-arqel-border)] px-4 py-3">
        <h1 className="text-lg font-semibold">{resource.pluralLabel}</h1>
        <div className="ml-auto flex flex-wrap items-center gap-2">{toolbarActions}</div>
      </header>

      <TableToolbar
        search={renderSearch()}
        filters={
          filters.length > 0 ? (
            <TableFilters
              filters={filters}
              values={filterValues}
              onChange={(name, value) => onFilterChange?.(name, value)}
              onClearAll={onClearFilters}
            />
          ) : null
        }
        selectedCount={selectedIds.length}
        bulkActions={bulkActions}
        onClearSelection={onSelectionChange ? () => onSelectionChange([]) : undefined}
      />

      <DataTable
        columns={columns}
        records={records as unknown as Array<{ id: RowId } & TRecord>}
        enableSelection={enableSelection}
        selectedIds={selectedIds}
        onSelectionChange={onSelectionChange}
        sort={normalisedSort}
        onSortChange={onSortChange}
        rowActions={rowActions ? (record) => rowActions(record as TRecord) : undefined}
        loading={loading}
        emptyState={emptyState}
      />

      {pagination && (
        <TablePagination
          meta={pagination}
          onPageChange={(page) => onPageChange?.(page)}
          onPerPageChange={onPerPageChange}
        />
      )}
    </section>
  );
}

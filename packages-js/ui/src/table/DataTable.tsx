/**
 * `<DataTable>` — generic table built on TanStack Table v8.
 *
 * Columns come from `ColumnSchema[]` (server-emitted). Two structural
 * extras live outside the schema list: a leading selection checkbox
 * (when `enableSelection`) and a trailing actions cell (when
 * `rowActions` resolver is provided).
 *
 * Selection model is controlled — pass `selectedIds` + `onSelectionChange`
 * — so callers (ResourceIndex, custom dashboards) keep ownership. Shift+
 * click on a row checkbox selects the range from the last clicked row,
 * matching the canonical Filament/Linear UX.
 */

import type { ColumnSchema, SortDirection, TableSort } from '@arqel-dev/types/tables';
import { type ColumnDef, flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import type { ReactNode } from 'react';
import { useCallback, useMemo, useRef } from 'react';
import { cn } from '../utils/cn.js';
import { TableCell } from './cells.js';

type RowId = string | number;

export interface DataTableRecord {
  id: RowId;
  [key: string]: unknown;
}

export interface DataTableProps<TRecord extends DataTableRecord> {
  columns: ColumnSchema[];
  records: TRecord[];

  enableSelection?: boolean;
  selectedIds?: ReadonlyArray<RowId>;
  onSelectionChange?: ((ids: RowId[]) => void) | undefined;

  sort?: TableSort | null;
  onSortChange?: ((column: string, direction: SortDirection) => void) | undefined;

  rowActions?: ((record: TRecord) => ReactNode) | undefined;

  loading?: boolean;
  emptyState?: ReactNode;

  className?: string;
}

export function DataTable<TRecord extends DataTableRecord>({
  columns,
  records,
  enableSelection = false,
  selectedIds = [],
  onSelectionChange,
  sort = null,
  onSortChange,
  rowActions,
  loading = false,
  emptyState,
  className,
}: DataTableProps<TRecord>) {
  const lastClickedIndexRef = useRef<number | null>(null);

  const visibleColumns = useMemo(() => columns.filter((col) => !col.hidden), [columns]);

  const tableColumns = useMemo<ColumnDef<TRecord>[]>(
    () =>
      visibleColumns.map((col) => ({
        id: col.name,
        accessorFn: (row) => (row as Record<string, unknown>)[col.name],
        header: () => col.label ?? col.name,
        cell: (info) => <TableCell column={col} value={info.getValue()} />,
        enableSorting: col.sortable,
      })),
    [visibleColumns],
  );

  const table = useReactTable({
    data: records,
    columns: tableColumns,
    getCoreRowModel: getCoreRowModel(),
  });

  const allSelected =
    enableSelection && records.length > 0 && selectedIds.length === records.length;
  const someSelected =
    enableSelection && selectedIds.length > 0 && selectedIds.length < records.length;

  const toggleAll = useCallback(() => {
    if (!onSelectionChange) return;
    if (allSelected) onSelectionChange([]);
    else onSelectionChange(records.map((r) => r.id));
  }, [allSelected, records, onSelectionChange]);

  const toggleRow = useCallback(
    (id: RowId, index: number, shiftKey: boolean) => {
      if (!onSelectionChange) return;
      const set = new Set<RowId>(selectedIds);
      const lastIndex = lastClickedIndexRef.current;

      if (shiftKey && lastIndex !== null) {
        const [from, to] = lastIndex < index ? [lastIndex, index] : [index, lastIndex];
        const shouldSelect = !set.has(id);
        for (let i = from; i <= to; i++) {
          const rowId = records[i]?.id;
          if (rowId === undefined) continue;
          if (shouldSelect) set.add(rowId);
          else set.delete(rowId);
        }
      } else if (set.has(id)) {
        set.delete(id);
      } else {
        set.add(id);
      }

      lastClickedIndexRef.current = index;
      onSelectionChange(Array.from(set));
    },
    [records, selectedIds, onSelectionChange],
  );

  const headerSortDirection = (columnName: string): SortDirection | null => {
    if (!sort || sort.column !== columnName) return null;
    return sort.direction === 'desc' ? 'desc' : 'asc';
  };

  return (
    <div className={cn('w-full overflow-x-auto', className)}>
      <table className="w-full border-collapse text-sm">
        <thead className="sticky top-0 bg-[var(--color-arqel-bg)]">
          {table.getHeaderGroups().map((group) => (
            <tr key={group.id} className="border-b border-[var(--color-arqel-border)]">
              {enableSelection && (
                <th scope="col" className="w-10 px-3 py-2 text-left">
                  <input
                    type="checkbox"
                    aria-label="Select all rows"
                    checked={allSelected}
                    ref={(el) => {
                      if (el) el.indeterminate = someSelected;
                    }}
                    onChange={toggleAll}
                  />
                </th>
              )}
              {group.headers.map((header) => {
                const col = visibleColumns.find((c) => c.name === header.column.id);
                const sortable = col?.sortable && onSortChange;
                const direction = headerSortDirection(header.column.id);
                return (
                  <th
                    key={header.id}
                    scope="col"
                    className={cn(
                      'px-3 py-2 text-left font-medium text-[var(--color-arqel-muted-fg)]',
                      col?.align === 'center' && 'text-center',
                      col?.align === 'end' && 'text-right',
                    )}
                    aria-sort={
                      direction === 'asc'
                        ? 'ascending'
                        : direction === 'desc'
                          ? 'descending'
                          : 'none'
                    }
                  >
                    {sortable ? (
                      <button
                        type="button"
                        className="inline-flex items-center gap-1 hover:text-[var(--color-arqel-fg)]"
                        onClick={() =>
                          onSortChange(header.column.id, direction === 'asc' ? 'desc' : 'asc')
                        }
                      >
                        {flexRender(header.column.columnDef.header, header.getContext())}
                        <span aria-hidden="true">
                          {direction === 'asc' ? '▲' : direction === 'desc' ? '▼' : '↕'}
                        </span>
                      </button>
                    ) : (
                      flexRender(header.column.columnDef.header, header.getContext())
                    )}
                  </th>
                );
              })}
              {rowActions && (
                <th scope="col" className="w-24 px-3 py-2 text-right" aria-label="Actions" />
              )}
            </tr>
          ))}
        </thead>
        <tbody>
          {loading && (
            <tr>
              <td
                colSpan={visibleColumns.length + (enableSelection ? 1 : 0) + (rowActions ? 1 : 0)}
                className="px-3 py-4 text-center text-[var(--color-arqel-muted-fg)]"
              >
                Loading…
              </td>
            </tr>
          )}
          {!loading && records.length === 0 && (
            <tr>
              <td
                colSpan={visibleColumns.length + (enableSelection ? 1 : 0) + (rowActions ? 1 : 0)}
                className="px-3 py-8 text-center text-[var(--color-arqel-muted-fg)]"
              >
                {emptyState ?? 'No records found.'}
              </td>
            </tr>
          )}
          {!loading &&
            table.getRowModel().rows.map((row, index) => {
              const record = row.original;
              const checked = selectedIds.includes(record.id);
              return (
                <tr
                  key={row.id}
                  data-selected={checked || undefined}
                  className={cn(
                    'border-b border-[var(--color-arqel-border)]',
                    checked && 'bg-[var(--color-arqel-muted)]',
                  )}
                >
                  {enableSelection && (
                    <td className="w-10 px-3 py-2">
                      <input
                        type="checkbox"
                        aria-label={`Select row ${record.id}`}
                        checked={checked}
                        onChange={(event) => {
                          const native = event.nativeEvent as MouseEvent;
                          toggleRow(record.id, index, native.shiftKey === true);
                        }}
                      />
                    </td>
                  )}
                  {row.getVisibleCells().map((cell) => {
                    const col = visibleColumns.find((c) => c.name === cell.column.id);
                    return (
                      <td
                        key={cell.id}
                        className={cn(
                          'px-3 py-2',
                          col?.align === 'center' && 'text-center',
                          col?.align === 'end' && 'text-right',
                          col?.hiddenOnMobile && 'hidden md:table-cell',
                        )}
                      >
                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                      </td>
                    );
                  })}
                  {rowActions && (
                    <td className="w-24 px-3 py-2 text-right">{rowActions(record)}</td>
                  )}
                </tr>
              );
            })}
        </tbody>
      </table>
    </div>
  );
}

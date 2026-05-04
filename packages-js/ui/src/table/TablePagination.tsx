/**
 * `<TablePagination>` — prev/next + range + per-page picker.
 *
 * Stateless: parent owns `currentPage` / `perPage` and handles transitions
 * (typically via Inertia `router.get` with `preserveState: true`).
 */

import type { PaginationMeta } from '@arqel-dev/types/resources';
import { Button } from '../action/Button.js';
import { cn } from '../utils/cn.js';

export interface TablePaginationProps {
  meta: PaginationMeta;
  onPageChange: (page: number) => void;
  onPerPageChange?: ((perPage: number) => void) | undefined;
  perPageOptions?: number[];
  className?: string;
}

export function TablePagination({
  meta,
  onPageChange,
  onPerPageChange,
  perPageOptions = [10, 25, 50, 100],
  className,
}: TablePaginationProps) {
  const from = (meta.currentPage - 1) * meta.perPage + 1;
  const to = Math.min(meta.currentPage * meta.perPage, meta.total);
  const isFirst = meta.currentPage <= 1;
  const isLast = meta.currentPage >= meta.lastPage;

  return (
    <nav
      className={cn(
        'flex flex-wrap items-center justify-between gap-2 border-t border-[var(--color-arqel-border)] px-3 py-2 text-sm',
        className,
      )}
      aria-label="Pagination"
    >
      <span className="text-[var(--color-arqel-muted-fg)]">
        {meta.total === 0 ? 'No results' : `${from}–${to} of ${meta.total}`}
      </span>
      <div className="flex items-center gap-2">
        {onPerPageChange && (
          <label className="flex items-center gap-1 text-xs text-[var(--color-arqel-muted-fg)]">
            Per page
            <select
              className="h-8 rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-input)] bg-[var(--color-arqel-bg)] px-2"
              value={meta.perPage}
              onChange={(e) => onPerPageChange(Number(e.target.value))}
            >
              {perPageOptions.map((n) => (
                <option key={n} value={n}>
                  {n}
                </option>
              ))}
            </select>
          </label>
        )}
        <Button
          variant="outline"
          size="sm"
          disabled={isFirst}
          onClick={() => onPageChange(meta.currentPage - 1)}
          aria-label="Previous page"
        >
          Prev
        </Button>
        <span className="text-xs text-[var(--color-arqel-muted-fg)]">
          {meta.currentPage} / {meta.lastPage}
        </span>
        <Button
          variant="outline"
          size="sm"
          disabled={isLast}
          onClick={() => onPageChange(meta.currentPage + 1)}
          aria-label="Next page"
        >
          Next
        </Button>
      </div>
    </nav>
  );
}

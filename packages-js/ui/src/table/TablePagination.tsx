/**
 * `<TablePagination>` — prev/next + range + per-page picker.
 *
 * Stateless: parent owns `currentPage` / `perPage` and handles transitions
 * (typically via Inertia `router.get` with `preserveState: true`).
 */

import { useArqelTranslations } from '@arqel-dev/react/utils';
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
  const t = useArqelTranslations();
  const from = (meta.currentPage - 1) * meta.perPage + 1;
  const to = Math.min(meta.currentPage * meta.perPage, meta.total);
  const isFirst = meta.currentPage <= 1;
  const isLast = meta.currentPage >= meta.lastPage;

  return (
    <nav
      className={cn(
        'flex flex-wrap items-center justify-between gap-2 border-t border-border px-3 py-2 text-sm',
        className,
      )}
      aria-label="Pagination"
    >
      <span className="text-muted-foreground">
        {meta.total === 0
          ? t('table.empty', 'No records found.')
          : t(
              'table.pagination.showing',
              // Fallback is rendered verbatim when the key is missing, so it must
              // already carry the interpolated values (the replacements map only
              // applies to a resolved dictionary string).
              `Showing ${from} to ${to} of ${meta.total} results`,
              { from, to, total: meta.total },
            )}
      </span>
      <div className="flex items-center gap-2">
        {onPerPageChange && (
          <label className="flex items-center gap-1 text-xs text-muted-foreground">
            {t('table.per_page', 'Per page')}
            {/* 44px touch target on mobile (WCAG 2.5.5); dense 32px on >=md. */}
            <select
              className="h-11 rounded-sm border border-[var(--input)] bg-background px-2 md:h-8"
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
        {/* Buttons use size="sm" (h-8) for the dense desktop bar; the responsive
            h-11 override lifts them to a 44px touch target below md. */}
        <Button
          variant="outline"
          size="sm"
          className="h-11 md:h-8"
          disabled={isFirst}
          onClick={() => onPageChange(meta.currentPage - 1)}
          aria-label={t('table.pagination.previous_page', 'Previous page')}
        >
          {t('table.pagination.previous', 'Prev')}
        </Button>
        <span className="text-xs text-muted-foreground">
          {meta.currentPage} / {meta.lastPage}
        </span>
        <Button
          variant="outline"
          size="sm"
          className="h-11 md:h-8"
          disabled={isLast}
          onClick={() => onPageChange(meta.currentPage + 1)}
          aria-label={t('table.pagination.next_page', 'Next page')}
        >
          {t('table.pagination.next', 'Next')}
        </Button>
      </div>
    </nav>
  );
}

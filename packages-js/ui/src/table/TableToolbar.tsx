/**
 * `<TableToolbar>` — search input + filters + bulk-action bar slot.
 *
 * The bulk-action bar appears only when `selectedCount > 0`. ResourceIndex
 * passes `bulkActions` rendered as a row of buttons; the toolbar just
 * positions them.
 */

import type { ReactNode } from 'react';
import { cn } from '../utils/cn.js';

export interface TableToolbarProps {
  search?: ReactNode;
  filters?: ReactNode;
  selectedCount?: number;
  bulkActions?: ReactNode;
  onClearSelection?: (() => void) | undefined;
  className?: string;
}

export function TableToolbar({
  search,
  filters,
  selectedCount = 0,
  bulkActions,
  onClearSelection,
  className,
}: TableToolbarProps) {
  return (
    <div className={cn('flex flex-col gap-3 px-3 py-3', className)}>
      <div className="flex flex-wrap items-end gap-2">
        {search}
        {filters}
      </div>
      {selectedCount > 0 && (
        <section
          className="flex flex-wrap items-center gap-2 rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-border)] bg-[var(--color-arqel-muted)] px-3 py-2"
          aria-label="Bulk actions"
        >
          <span className="text-sm font-medium">{selectedCount} selected</span>
          <div className="ml-auto flex flex-wrap items-center gap-2">
            {bulkActions}
            {onClearSelection && (
              <button
                type="button"
                className="text-sm text-[var(--color-arqel-muted-fg)] hover:underline"
                onClick={onClearSelection}
              >
                Clear
              </button>
            )}
          </div>
        </section>
      )}
    </div>
  );
}

/**
 * `<TableCard>` — minimal mini-table widget rendered inside a dashboard.
 *
 * Mirrors the payload produced by `Arqel\Widgets\TableWidget::data()`:
 * `{ columns, records, limit, seeAllUrl, loadError? }`. The component is
 * intentionally schema-light — each column carries `name` + `label` and
 * cells extract `record[column.name]` verbatim. Renders a `role="alert"`
 * panel when the PHP side surfaced a `loadError` instead of trying to
 * draw an empty table.
 */

import { cn } from '../utils/cn.js';
import { WidgetWrapper } from './WidgetWrapper.js';

export interface TableCardColumn {
  name: string;
  label?: string | null;
}

export interface TableCardRecord {
  [key: string]: unknown;
}

export interface TableCardWidget {
  name: string;
  type: 'table';
  heading?: string | null;
  description?: string | null;
  columns: TableCardColumn[];
  records: TableCardRecord[];
  limit: number;
  seeAllUrl?: string | null;
  loadError?: string | null;
}

export interface TableCardProps {
  widget: TableCardWidget;
  className?: string | undefined;
}

export function TableCard({ widget, className }: TableCardProps) {
  return (
    <WidgetWrapper
      name={widget.name}
      heading={widget.heading ?? null}
      description={widget.description ?? null}
      className={className}
    >
      {widget.loadError ? (
        <div role="alert" className="text-sm text-red-600">
          {widget.loadError}
        </div>
      ) : (
        <>
          <div className="overflow-x-auto">
            <table className={cn('w-full border-collapse text-sm', 'text-[var(--color-arqel-fg)]')}>
              <thead>
                <tr className="border-b border-[var(--color-arqel-border)] text-left">
                  {widget.columns.map((column) => (
                    <th
                      key={column.name}
                      scope="col"
                      className="px-2 py-1.5 font-medium text-[var(--color-arqel-muted-fg)]"
                    >
                      {column.label ?? column.name}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {widget.records.map((record, rowIndex) => (
                  <tr
                    // biome-ignore lint/suspicious/noArrayIndexKey: records may lack a stable id
                    key={rowIndex}
                    className="border-b border-[var(--color-arqel-border)] last:border-0"
                  >
                    {widget.columns.map((column) => (
                      <td key={column.name} className="px-2 py-1.5">
                        {formatCell(record[column.name])}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {widget.seeAllUrl && (
            <div className="mt-3 text-right">
              <a href={widget.seeAllUrl} className="text-sm text-[var(--color-arqel-primary)]">
                See all →
              </a>
            </div>
          )}
        </>
      )}
    </WidgetWrapper>
  );
}

function formatCell(value: unknown): string {
  if (value === null || value === undefined) return '';
  if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
    return String(value);
  }
  try {
    return JSON.stringify(value);
  } catch {
    return '';
  }
}

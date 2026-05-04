/**
 * `<HasManyReadonly>` — flat read-only list of related records.
 *
 * Phase 1 ships a stacked list (label + id). The full inline-table
 * editor lives behind FIELDS-JS-003 follow-up since editable nested
 * collections require their own change-set tracking.
 */

import type { HasManyFieldSchema } from '@arqel-dev/types/fields';
import type { FieldRendererProps } from '@arqel-dev/ui/form';

interface RelatedRecord {
  id: string | number;
  label?: string;
  [key: string]: unknown;
}

export function HasManyReadonly({ field, value, inputId, describedBy }: FieldRendererProps) {
  const f = field as HasManyFieldSchema;
  const items: RelatedRecord[] = Array.isArray(value) ? (value as RelatedRecord[]) : [];

  if (items.length === 0) {
    return (
      <p
        id={inputId}
        aria-describedby={describedBy}
        className="text-sm text-[var(--color-arqel-muted-fg)]"
      >
        No {f.props.relatedResource} linked.
      </p>
    );
  }

  return (
    <ul
      id={inputId}
      aria-describedby={describedBy}
      className="divide-y divide-[var(--color-arqel-border)] rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-border)]"
    >
      {items.map((item) => (
        <li
          key={String(item.id)}
          className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
        >
          <span>{item.label ?? `#${item.id}`}</span>
          <span className="font-mono text-xs text-[var(--color-arqel-muted-fg)]">{item.id}</span>
        </li>
      ))}
    </ul>
  );
}

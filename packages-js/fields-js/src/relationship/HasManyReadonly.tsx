/**
 * `<HasManyReadonly>` — flat read-only list of related records.
 *
 * Phase 1 ships a stacked list (label + id). The full inline-table
 * editor lives behind FIELDS-JS-003 follow-up since editable nested
 * collections require their own change-set tracking.
 */

import { useArqelTranslations } from '@arqel-dev/react/utils';
import type { HasManyFieldSchema } from '@arqel-dev/types/fields';
import type { FieldRendererProps } from '@arqel-dev/ui/form';

interface RelatedRecord {
  id: string | number;
  label?: string;
  [key: string]: unknown;
}

export function HasManyReadonly({ field, value, inputId, describedBy }: FieldRendererProps) {
  const f = field as HasManyFieldSchema;
  const t = useArqelTranslations();
  const items: RelatedRecord[] = Array.isArray(value) ? (value as RelatedRecord[]) : [];

  if (items.length === 0) {
    return (
      <p id={inputId} aria-describedby={describedBy} className="text-sm text-muted-foreground">
        {t('arqel.fields.has_many_empty', 'No :resource linked.', {
          resource: f.props.relatedResource,
        })}
      </p>
    );
  }

  return (
    <ul
      id={inputId}
      aria-describedby={describedBy}
      className="divide-y divide-[var(--border)] rounded-sm border border-border"
    >
      {items.map((item) => (
        <li
          key={String(item.id)}
          className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
        >
          <span>{item.label ?? `#${item.id}`}</span>
          <span className="font-mono text-xs text-muted-foreground">{item.id}</span>
        </li>
      ))}
    </ul>
  );
}

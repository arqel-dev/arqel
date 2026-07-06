/**
 * `<RelationManagerPanel>` — one relation-manager tab on a parent's edit
 * page: a `DataTable` of related records with ability-gated toolbar (New /
 * Attach) and per-row Edit actions. Presentational — mutation (create,
 * attach, edit) is delegated to the parent page via the callbacks; this
 * component owns no Inertia visits itself.
 *
 * `relation.table` is the PHP `Table::toArray()` payload (untyped on the
 * wire per `RelationManagerProps`'s docblock); the column list lives at
 * `relation.table.columns`, mirroring how `ArqelIndexPage`/`ResourceIndex`
 * feed `DataTable` from `ResourceIndexProps.columns`.
 *
 * Labels are literal English fallbacks for now rather than routed through
 * `useArqelTranslations` — this panel can render standalone (e.g. inside a
 * modal/tab shell built ahead of full Inertia page context), so it can't
 * assume `usePage()` resolves. Task 11's tabs wrapper mounts inside the full
 * page tree and can wire `arqel::relations.*` keys once that context exists.
 */

import type { RelationManagerProps } from '@arqel-dev/types/relations';
import type { ColumnSchema } from '@arqel-dev/types/tables';
import { Button } from '../action/Button.js';
import { DataTable, type DataTableRecord } from '../table/DataTable.js';

export interface RelationManagerPanelProps {
  relation: RelationManagerProps;
  parentSlug: string;
  parentId: string | number;
  records: unknown[];
  onEdit(id: string | number): void;
  onCreate(): void;
  onAttach(): void;
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
  records,
  onEdit,
  onCreate,
  onAttach,
}: RelationManagerPanelProps) {
  const { abilities, type } = relation;
  const columns = relationColumns(relation.table);

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
      <DataTable<DataTableRecord>
        columns={columns}
        records={records as DataTableRecord[]}
        rowActions={
          abilities.update
            ? (record) => (
                <Button type="button" size="sm" variant="ghost" onClick={() => onEdit(record.id)}>
                  Edit
                </Button>
              )
            : undefined
        }
      />
    </div>
  );
}

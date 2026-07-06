/**
 * Relation manager payload — the shape returned by
 * `Arqel\Core\Relations\RelationManager::toArray()`.
 *
 * `table` and `fields` are typed `unknown`/`unknown[]` here (matching the
 * PHP method's own untyped return) rather than `TableSchema`/`FieldSchema[]`:
 * `arqel-dev/types` mirrors `arqel-dev/core`'s deliberate decoupling from
 * `arqel-dev/table`/`arqel-dev/form` (see `RelationManager`'s class docblock).
 * Consumers narrow as needed when wiring the real `DataTable`.
 */

export interface RelationManagerAbilities {
  create: boolean;
  update: boolean;
  delete: boolean;
  attach: boolean;
  detach: boolean;
}

export interface RelationManagerProps {
  slug: string;
  label: string;
  type: 'hasMany' | 'morphMany' | 'belongsToMany';
  table: unknown;
  fields: unknown[];
  abilities: RelationManagerAbilities;
}

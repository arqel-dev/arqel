/**
 * Resource-level Inertia payloads — the shapes returned by
 * `InertiaDataBuilder::buildIndexData/CreateData/EditData/ShowData`.
 */

import type { ResourceActions } from './actions.js';
import type { FieldSchema } from './fields.js';
import type { ColumnSchema, FilterSchema, TableSort } from './tables.js';

/**
 * Loose record shape — apps that opt into
 * `spatie/laravel-typescript-transformer` (TYPES-003) replace this
 * with strongly-typed `interface User { id: number; ... }` etc.
 */
export type RecordType = Record<string, unknown> & {
  id: number | string;
  arqel?: {
    title?: string;
    subtitle?: string | null;
  };
};

/**
 * Resource metadata block (`InertiaDataBuilder::resourceMeta`).
 */
export interface ResourceMeta {
  class: string;
  slug: string;
  label: string;
  pluralLabel: string;
  navigationIcon: string | null;
  navigationGroup: string | null;
  /**
   * Panel base path (leading slash, no trailing). Server emits this
   * via `InertiaDataBuilder::resolvePanelPath`. Edit/Create pages
   * use it to construct absolute submit URLs.
   *
   * Optional for backwards compatibility with v0.9.x payloads that
   * predate this field.
   */
  panelPath?: string;
}

/**
 * Pagination payload (`Illuminate\Pagination\LengthAwarePaginator`
 * stripped for Inertia).
 */
export interface PaginationMeta {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
}

/**
 * Index page props — the rich Table flavour, used when
 * `Resource::table()` returns an Arqel Table.
 */
export interface ResourceIndexProps<TRecord extends RecordType = RecordType> {
  resource: ResourceMeta;
  records: TRecord[];
  pagination: PaginationMeta | null;
  columns: ColumnSchema[];
  filters: FilterSchema[];
  actions: ResourceActions;
  search: string | null;
  sort: TableSort;
}

/**
 * Index page props — plain (no Table) flavour.
 */
export interface PlainResourceIndexProps<TRecord extends RecordType = RecordType> {
  resource: ResourceMeta;
  records: TRecord[];
  pagination: PaginationMeta;
  fields: FieldSchema[];
}

/**
 * Create page props (no record context).
 */
export interface ResourceCreateProps {
  resource: ResourceMeta;
  record: null;
  fields: FieldSchema[];
}

/**
 * Edit page props — record + fields (record-aware visibility +
 * readonly applied server-side).
 */
export interface ResourceEditProps<TRecord extends RecordType = RecordType> {
  resource: ResourceMeta;
  record: TRecord;
  recordTitle: string;
  recordSubtitle: string | null;
  fields: FieldSchema[];
}

/**
 * Show / detail page props — same shape as edit; React renders
 * fields read-only.
 */
export type ResourceDetailProps<TRecord extends RecordType = RecordType> =
  ResourceEditProps<TRecord>;

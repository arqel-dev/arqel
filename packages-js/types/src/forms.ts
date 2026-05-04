/**
 * Form schema mirroring `arqel-dev/form` PHP serialisation.
 *
 * The `Form::toArray()` payload is a heterogeneous list of entries
 * tagged by `kind`: a `field` carries `{name, type}` (the React
 * side resolves the full Field schema from the Resource fields
 * array), while a `layout` entry carries `{type, component,
 * columnSpan, props}` plus the component's own children.
 */

import type { FieldSchema } from './fields.js';

export type LayoutType = 'section' | 'fieldset' | 'grid' | 'columns' | 'group' | 'tabs' | 'tab';

export interface FieldEntry {
  kind: 'field';
  name: string;
  type: string;
}

interface LayoutBase<TType extends LayoutType, TProps> {
  kind: 'layout';
  type: TType;
  component: string;
  columnSpan: number | string;
  props: TProps;
  /** Schema may include further entries (recursive). */
  schema?: SchemaEntry[];
}

/* ─── Layout props ──────────────────────────────────────────────── */

export interface SectionProps {
  heading: string;
  description?: string;
  icon?: string;
  collapsible?: boolean;
  collapsed?: boolean;
  columns: number;
  compact?: boolean;
  aside?: boolean;
}

export interface FieldsetProps {
  legend: string;
  columns: number;
}

export interface GridProps {
  columns: number | Record<string, number>;
  gap?: string;
}

export interface ColumnsProps {
  columns: 2;
}

export type GroupOrientation = 'horizontal' | 'vertical';

export interface GroupProps {
  orientation: GroupOrientation;
}

export type TabsOrientation = 'horizontal' | 'vertical';

export interface TabsProps {
  defaultTab: string | null;
  orientation: TabsOrientation;
}

export interface TabProps {
  id: string;
  label: string;
  icon?: string;
  badge?: number;
}

export type SectionEntry = LayoutBase<'section', SectionProps>;
export type FieldsetEntry = LayoutBase<'fieldset', FieldsetProps>;
export type GridEntry = LayoutBase<'grid', GridProps>;
export type ColumnsEntry = LayoutBase<'columns', ColumnsProps>;
export type GroupEntry = LayoutBase<'group', GroupProps>;
export type TabsEntry = LayoutBase<'tabs', TabsProps>;
export type TabEntry = LayoutBase<'tab', TabProps>;

export type LayoutEntry =
  | SectionEntry
  | FieldsetEntry
  | GridEntry
  | ColumnsEntry
  | GroupEntry
  | TabsEntry
  | TabEntry;

export type SchemaEntry = FieldEntry | LayoutEntry;

/**
 * The full `Form::toArray()` payload.
 */
export interface FormSchema {
  schema: SchemaEntry[];
  columns: number;
  model: string | null;
  inline: boolean;
  disabled: boolean;
}

/**
 * Type guard narrowing on `kind`.
 */
export function isLayoutEntry(entry: SchemaEntry): entry is LayoutEntry {
  return entry.kind === 'layout';
}

export function isFieldEntry(entry: SchemaEntry): entry is FieldEntry {
  return entry.kind === 'field';
}

/**
 * Resolve a `FieldEntry`'s full schema by name from a flat list
 * of Field schemas. Returns `null` when the field is missing —
 * authoring sites typically render a fallback.
 */
export function resolveFieldEntry(entry: FieldEntry, fields: FieldSchema[]): FieldSchema | null {
  return fields.find((field) => field.name === entry.name) ?? null;
}

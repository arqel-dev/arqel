/**
 * Table column schema mirroring `arqel/table` PHP serialisation.
 */

export type ColumnType =
  | 'text'
  | 'badge'
  | 'boolean'
  | 'date'
  | 'number'
  | 'icon'
  | 'image'
  | 'relationship'
  | 'computed';

export type ColumnAlign = 'start' | 'center' | 'end';

export type DateColumnMode = 'date' | 'datetime' | 'since';

export type ImageColumnShape = 'square' | 'circular';

export type SortDirection = 'asc' | 'desc';

interface ColumnBase<TType extends ColumnType, TProps = Record<string, never>> {
  type: TType;
  name: string;
  label: string | null;
  sortable: boolean;
  searchable: boolean;
  copyable: boolean;
  hidden: boolean;
  hiddenOnMobile: boolean;
  align: ColumnAlign;
  width: string | null;
  tooltip: string | null;
  /** Resolved URL for clickable cells. */
  url?: string;
  props: TProps;
}

/* ─── Per-type props ────────────────────────────────────────────── */

export interface TextColumnProps {
  truncate?: number;
  color?: string;
  weight?: 'normal' | 'medium' | 'bold';
}

export interface BadgeOption {
  value: string | number | boolean;
  label?: string;
  color?: string;
  icon?: string;
}

export interface BadgeColumnProps {
  options?: BadgeOption[];
  pill?: boolean;
}

export interface BooleanColumnProps {
  trueIcon?: string;
  falseIcon?: string;
  trueColor?: string;
  falseColor?: string;
}

export interface DateColumnProps {
  mode: DateColumnMode;
  format?: string;
  timezone?: string;
}

export interface NumberColumnProps {
  decimals?: number;
  thousandsSeparator?: string;
  decimalSeparator?: string;
  prefix?: string;
  suffix?: string;
}

export interface IconColumnProps {
  icon: string;
  color?: string;
  size?: 'sm' | 'md' | 'lg';
}

export interface ImageColumnProps {
  shape?: ImageColumnShape;
  size?: number;
  rounded?: boolean;
}

export interface RelationshipColumnProps {
  relationship: string;
  attribute: string;
}

export interface ComputedColumnProps {
  /** Closure-resolved values are emitted directly per row. */
  placeholder?: string;
}

/* ─── Discriminated union ───────────────────────────────────────── */

export type TextColumnSchema = ColumnBase<'text', TextColumnProps>;
export type BadgeColumnSchema = ColumnBase<'badge', BadgeColumnProps>;
export type BooleanColumnSchema = ColumnBase<'boolean', BooleanColumnProps>;
export type DateColumnSchema = ColumnBase<'date', DateColumnProps>;
export type NumberColumnSchema = ColumnBase<'number', NumberColumnProps>;
export type IconColumnSchema = ColumnBase<'icon', IconColumnProps>;
export type ImageColumnSchema = ColumnBase<'image', ImageColumnProps>;
export type RelationshipColumnSchema = ColumnBase<'relationship', RelationshipColumnProps>;
export type ComputedColumnSchema = ColumnBase<'computed', ComputedColumnProps>;

export type ColumnSchema =
  | TextColumnSchema
  | BadgeColumnSchema
  | BooleanColumnSchema
  | DateColumnSchema
  | NumberColumnSchema
  | IconColumnSchema
  | ImageColumnSchema
  | RelationshipColumnSchema
  | ComputedColumnSchema;

/* ─── Filter schema ─────────────────────────────────────────────── */

export type FilterType = 'select' | 'multiSelect' | 'dateRange' | 'text' | 'ternary' | 'scope';

interface FilterBase<TType extends FilterType, TProps = Record<string, never>> {
  type: TType;
  name: string;
  label: string | null;
  persist: boolean;
  default: unknown;
  props: TProps;
}

export interface SelectFilterProps {
  options: { value: string | number; label: string }[];
}

export interface DateRangeFilterProps {
  minDate?: string;
  maxDate?: string;
}

export type TernaryState = 'true' | 'false' | 'all';

export interface TernaryFilterProps {
  trueLabel?: string;
  falseLabel?: string;
  allLabel?: string;
}

export type SelectFilterSchema = FilterBase<'select', SelectFilterProps>;
export type MultiSelectFilterSchema = FilterBase<'multiSelect', SelectFilterProps>;
export type DateRangeFilterSchema = FilterBase<'dateRange', DateRangeFilterProps>;
export type TextFilterSchema = FilterBase<'text'>;
export type TernaryFilterSchema = FilterBase<'ternary', TernaryFilterProps>;
export type ScopeFilterSchema = FilterBase<'scope'>;

export type FilterSchema =
  | SelectFilterSchema
  | MultiSelectFilterSchema
  | DateRangeFilterSchema
  | TextFilterSchema
  | TernaryFilterSchema
  | ScopeFilterSchema;

/* ─── Sort + selection ──────────────────────────────────────────── */

export interface TableSort {
  column: string | null;
  direction: SortDirection | null;
}

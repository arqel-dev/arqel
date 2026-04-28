/**
 * Field schema mirroring `Arqel\Core\Support\FieldSchemaSerializer`
 * output. The discriminated union narrows on `type`; per-type
 * `props` carry the type-specific metadata emitted by each Field's
 * `getTypeSpecificProps()`.
 */

export type FieldType =
  | 'text'
  | 'textarea'
  | 'email'
  | 'url'
  | 'password'
  | 'slug'
  | 'number'
  | 'currency'
  | 'boolean'
  | 'toggle'
  | 'select'
  | 'multiSelect'
  | 'radio'
  | 'belongsTo'
  | 'hasMany'
  | 'date'
  | 'dateTime'
  | 'file'
  | 'image'
  | 'color'
  | 'hidden';

export interface FieldValidation {
  rules: string[];
  messages: Record<string, string>;
  attribute: string | null;
}

/**
 * Per-context visibility flags (mirrors `HasVisibility::isVisibleIn`).
 * `canSee` is the per-user/record gate from `HasAuthorization`.
 */
export interface FieldVisibility {
  create: boolean;
  edit: boolean;
  detail: boolean;
  table: boolean;
  canSee: boolean;
}

/**
 * Common shape every Field carries before the discriminated `props`.
 */
interface FieldBase<TType extends FieldType, TProps> {
  type: TType;
  name: string;
  label: string | null;
  component: string | null;
  required: boolean;
  readonly: boolean;
  disabled: boolean;
  placeholder: string | null;
  helperText: string | null;
  defaultValue: unknown;
  columnSpan: number | string;
  live: boolean;
  liveDebounce: number | null;
  validation: FieldValidation;
  visibility: FieldVisibility;
  dependsOn: string[];
  props: TProps;
}

/* ─── Per-type props ────────────────────────────────────────────── */

export interface TextFieldProps {
  maxLength?: number;
  minLength?: number;
  pattern?: string;
  autocomplete?: string;
  mask?: string;
}

export interface NumberFieldProps {
  min?: number;
  max?: number;
  step?: number | string;
  integer?: boolean;
  decimals?: number;
}

export interface CurrencyFieldProps extends NumberFieldProps {
  prefix: string;
  suffix?: string;
  thousandsSeparator: string;
  decimalSeparator: string;
}

export interface BooleanFieldProps {
  inline?: boolean;
}

export interface ToggleFieldProps {
  onLabel?: string;
  offLabel?: string;
}

export interface SelectOption {
  value: string | number;
  label: string;
}

export interface SelectFieldProps {
  options: SelectOption[] | Record<string, string>;
  searchable?: boolean;
  multiple?: boolean;
  placeholder?: string;
}

export interface MultiSelectFieldProps extends SelectFieldProps {
  multiple: true;
}

export interface RadioFieldProps {
  options: SelectOption[];
  inline?: boolean;
}

export interface BelongsToFieldProps {
  relatedResource: string;
  relationship: string;
  searchable: boolean;
  searchColumns: string[];
  preload: boolean;
  /** Set client-side from the panel routes. */
  searchRoute?: string;
}

export interface HasManyFieldProps {
  relatedResource: string;
  relationship: string;
  canAddRecords?: boolean;
  canEditRecords?: boolean;
}

export interface DateFieldProps {
  format: string;
  displayFormat: string;
  minDate?: string;
  maxDate?: string;
  closeOnDateSelection?: boolean;
  timezone?: string;
}

export interface DateTimeFieldProps extends DateFieldProps {
  seconds?: boolean;
}

export interface FileFieldProps {
  disk: string;
  directory?: string;
  visibility?: 'public' | 'private';
  maxSize?: number;
  acceptedFileTypes?: string[];
  multiple?: boolean;
  reorderable?: boolean;
  strategy?: string;
  /** Set client-side from the panel routes. */
  uploadRoute?: string;
}

export interface ImageFieldProps extends FileFieldProps {
  aspectRatio?: number;
  crop?: boolean;
}

export type ColorFormat = 'hex' | 'rgb' | 'hsl';

export interface ColorFieldProps {
  presets?: string[];
  format?: ColorFormat;
  alpha?: boolean;
}

export interface SlugFieldProps extends TextFieldProps {
  reservedSlugs?: string[];
}

/* ─── Discriminated union ───────────────────────────────────────── */

export type TextFieldSchema = FieldBase<'text', TextFieldProps>;
export type TextareaFieldSchema = FieldBase<'textarea', TextFieldProps>;
export type EmailFieldSchema = FieldBase<'email', TextFieldProps>;
export type UrlFieldSchema = FieldBase<'url', TextFieldProps>;
export type PasswordFieldSchema = FieldBase<'password', TextFieldProps>;
export type SlugFieldSchema = FieldBase<'slug', SlugFieldProps>;
export type NumberFieldSchema = FieldBase<'number', NumberFieldProps>;
export type CurrencyFieldSchema = FieldBase<'currency', CurrencyFieldProps>;
export type BooleanFieldSchema = FieldBase<'boolean', BooleanFieldProps>;
export type ToggleFieldSchema = FieldBase<'toggle', ToggleFieldProps>;
export type SelectFieldSchema = FieldBase<'select', SelectFieldProps>;
export type MultiSelectFieldSchema = FieldBase<'multiSelect', MultiSelectFieldProps>;
export type RadioFieldSchema = FieldBase<'radio', RadioFieldProps>;
export type BelongsToFieldSchema = FieldBase<'belongsTo', BelongsToFieldProps>;
export type HasManyFieldSchema = FieldBase<'hasMany', HasManyFieldProps>;
export type DateFieldSchema = FieldBase<'date', DateFieldProps>;
export type DateTimeFieldSchema = FieldBase<'dateTime', DateTimeFieldProps>;
export type FileFieldSchema = FieldBase<'file', FileFieldProps>;
export type ImageFieldSchema = FieldBase<'image', ImageFieldProps>;
export type ColorFieldSchema = FieldBase<'color', ColorFieldProps>;
export type HiddenFieldSchema = FieldBase<'hidden', Record<string, never>>;

export type FieldSchema =
  | TextFieldSchema
  | TextareaFieldSchema
  | EmailFieldSchema
  | UrlFieldSchema
  | PasswordFieldSchema
  | SlugFieldSchema
  | NumberFieldSchema
  | CurrencyFieldSchema
  | BooleanFieldSchema
  | ToggleFieldSchema
  | SelectFieldSchema
  | MultiSelectFieldSchema
  | RadioFieldSchema
  | BelongsToFieldSchema
  | HasManyFieldSchema
  | DateFieldSchema
  | DateTimeFieldSchema
  | FileFieldSchema
  | ImageFieldSchema
  | ColorFieldSchema
  | HiddenFieldSchema;

/* ─── Type guards ───────────────────────────────────────────────── */

export function isFieldType<T extends FieldType>(
  field: FieldSchema,
  type: T,
): field is Extract<FieldSchema, { type: T }> {
  return field.type === type;
}

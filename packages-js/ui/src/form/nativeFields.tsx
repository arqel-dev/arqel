/**
 * Native HTML fallback inputs — used until `@arqel-dev/fields` registers
 * richer impls. One renderer per FieldSchema discriminant.
 */

import type {
  BelongsToFieldSchema,
  BooleanFieldSchema,
  CurrencyFieldSchema,
  DateFieldSchema,
  DateTimeFieldSchema,
  EmailFieldSchema,
  FieldSchema,
  HasManyFieldSchema,
  MultiSelectFieldSchema,
  NumberFieldSchema,
  PasswordFieldSchema,
  RadioFieldSchema,
  SelectFieldSchema,
  SlugFieldSchema,
  TextareaFieldSchema,
  TextFieldSchema,
  ToggleFieldSchema,
  UrlFieldSchema,
} from '@arqel-dev/types/fields';
import { cn } from '../utils/cn.js';

export interface NativeProps {
  field: FieldSchema;
  value: unknown;
  onChange: (value: unknown) => void;
  disabled?: boolean | undefined;
  errors?: string[] | undefined;
  className?: string | undefined;
  inputId?: string | undefined;
  describedBy?: string | undefined;
}

const inputClasses = cn(
  'h-9 rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-input)]',
  'bg-[var(--color-arqel-bg)] px-3 text-sm',
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)]',
  'disabled:cursor-not-allowed disabled:opacity-50',
);

export function NativeFieldInput(props: NativeProps) {
  const { field } = props;

  switch (field.type) {
    case 'text':
    case 'email':
    case 'url':
    case 'password':
    case 'slug':
      return <TextLike {...props} field={field} />;
    case 'textarea':
      return <Textarea {...props} field={field} />;
    case 'number':
    case 'currency':
      return <NumericInput {...props} field={field} />;
    case 'boolean':
    case 'toggle':
      return <Checkbox {...props} field={field} />;
    case 'select':
      return <SelectInput {...props} field={field} />;
    case 'multiSelect':
    case 'hasMany':
      return <MultiSelectInput {...props} field={field} />;
    case 'radio':
      return <RadioGroup {...props} field={field} />;
    case 'belongsTo':
      return <BelongsToInput {...props} field={field} />;
    case 'date':
    case 'dateTime':
      return <DateInput {...props} field={field} />;
    case 'file':
    case 'image':
      return <FileInput {...props} />;
    default:
      return null;
  }
}

/* ─── primitives ─────────────────────────────────────────────────── */

type TextLikeField =
  | TextFieldSchema
  | EmailFieldSchema
  | UrlFieldSchema
  | PasswordFieldSchema
  | SlugFieldSchema;

function TextLike({
  field,
  value,
  onChange,
  disabled,
  inputId,
  describedBy,
}: NativeProps & { field: TextLikeField }) {
  const htmlType =
    field.type === 'email'
      ? 'email'
      : field.type === 'password'
        ? 'password'
        : field.type === 'url'
          ? 'url'
          : 'text';
  const isDisabled = disabled || field.disabled || field.readonly;
  return (
    <input
      id={inputId}
      type={htmlType}
      className={inputClasses}
      value={typeof value === 'string' ? value : ((value ?? '')?.toString() ?? '')}
      placeholder={field.placeholder ?? undefined}
      disabled={isDisabled}
      readOnly={field.readonly === true}
      required={field.required === true}
      aria-describedby={describedBy}
      onChange={(e) => onChange(e.target.value)}
    />
  );
}

function Textarea({
  field,
  value,
  onChange,
  disabled,
  inputId,
  describedBy,
}: NativeProps & { field: TextareaFieldSchema }) {
  return (
    <textarea
      id={inputId}
      className={cn(inputClasses, 'h-auto min-h-[5rem] py-2')}
      value={typeof value === 'string' ? value : ''}
      placeholder={field.placeholder ?? undefined}
      disabled={disabled || field.disabled || field.readonly}
      readOnly={field.readonly === true}
      required={field.required === true}
      rows={4}
      aria-describedby={describedBy}
      onChange={(e) => onChange(e.target.value)}
    />
  );
}

function NumericInput({
  field,
  value,
  onChange,
  disabled,
  inputId,
  describedBy,
}: NativeProps & { field: NumberFieldSchema | CurrencyFieldSchema }) {
  return (
    <input
      id={inputId}
      type="number"
      className={inputClasses}
      value={value === null || value === undefined ? '' : String(value)}
      placeholder={field.placeholder ?? undefined}
      disabled={disabled || field.disabled || field.readonly}
      readOnly={field.readonly === true}
      required={field.required === true}
      aria-describedby={describedBy}
      onChange={(e) => {
        const raw = e.target.value;
        if (raw === '') {
          onChange(null);
          return;
        }
        const num = Number(raw);
        onChange(Number.isNaN(num) ? null : num);
      }}
    />
  );
}

function Checkbox({
  field,
  value,
  onChange,
  disabled,
  inputId,
  describedBy,
}: NativeProps & { field: BooleanFieldSchema | ToggleFieldSchema }) {
  return (
    <input
      id={inputId}
      type="checkbox"
      className="h-4 w-4 rounded border-[var(--color-arqel-input)]"
      checked={value === true}
      disabled={disabled || field.disabled || field.readonly}
      aria-describedby={describedBy}
      onChange={(e) => onChange(e.target.checked)}
    />
  );
}

function normaliseOptions(
  options: SelectFieldSchema['props']['options'] | undefined,
): Array<{ value: string | number; label: string }> {
  if (!options) return [];
  if (Array.isArray(options)) return options;
  return Object.entries(options).map(([value, label]) => ({ value, label }));
}

function SelectInput({
  field,
  value,
  onChange,
  disabled,
  inputId,
  describedBy,
}: NativeProps & { field: SelectFieldSchema }) {
  const options = normaliseOptions(field.props.options);
  return (
    <select
      id={inputId}
      className={inputClasses}
      value={value === null || value === undefined ? '' : String(value)}
      disabled={disabled || field.disabled || field.readonly}
      required={field.required === true}
      aria-describedby={describedBy}
      onChange={(e) => onChange(e.target.value === '' ? null : e.target.value)}
    >
      {field.placeholder && <option value="">{field.placeholder}</option>}
      {options.map((opt) => (
        <option key={opt.value} value={opt.value}>
          {opt.label}
        </option>
      ))}
    </select>
  );
}

function MultiSelectInput({
  field,
  value,
  onChange,
  disabled,
  inputId,
  describedBy,
}: NativeProps & { field: MultiSelectFieldSchema | HasManyFieldSchema }) {
  const arr = Array.isArray(value) ? (value as Array<string | number>) : [];
  const options = field.type === 'multiSelect' ? normaliseOptions(field.props.options) : [];
  return (
    <select
      id={inputId}
      multiple
      className={cn(inputClasses, 'h-auto min-h-[6rem] py-1')}
      value={arr.map(String)}
      disabled={disabled || field.disabled || field.readonly}
      aria-describedby={describedBy}
      onChange={(e) => {
        const next = Array.from(e.target.selectedOptions, (o) => o.value);
        onChange(next);
      }}
    >
      {options.map((opt) => (
        <option key={opt.value} value={opt.value}>
          {opt.label}
        </option>
      ))}
    </select>
  );
}

function RadioGroup({
  field,
  value,
  onChange,
  disabled,
  inputId,
  describedBy,
}: NativeProps & { field: RadioFieldSchema }) {
  return (
    <div
      id={inputId}
      className="flex flex-col gap-1"
      aria-describedby={describedBy}
      role="radiogroup"
    >
      {(field.props.options ?? []).map((opt) => (
        <label key={opt.value} className="inline-flex items-center gap-2 text-sm">
          <input
            type="radio"
            name={field.name}
            value={String(opt.value)}
            checked={String(value ?? '') === String(opt.value)}
            disabled={disabled || field.disabled || field.readonly}
            onChange={() => onChange(opt.value)}
          />
          {opt.label}
        </label>
      ))}
    </div>
  );
}

function BelongsToInput({
  field,
  value,
  onChange,
  disabled,
  inputId,
  describedBy,
}: NativeProps & { field: BelongsToFieldSchema }) {
  // Phase 1 fallback: native text input. The full async-search picker
  // ships in `@arqel-dev/fields/BelongsToField` and registers via FieldRegistry.
  return (
    <input
      id={inputId}
      type="text"
      className={inputClasses}
      value={value === null || value === undefined ? '' : String(value)}
      placeholder={field.placeholder ?? `Search ${field.props.relatedResource}…`}
      disabled={disabled || field.disabled || field.readonly}
      readOnly={field.readonly === true}
      aria-describedby={describedBy}
      onChange={(e) => onChange(e.target.value === '' ? null : e.target.value)}
    />
  );
}

function DateInput({
  field,
  value,
  onChange,
  disabled,
  inputId,
  describedBy,
}: NativeProps & { field: DateFieldSchema | DateTimeFieldSchema }) {
  return (
    <input
      id={inputId}
      type={field.type === 'dateTime' ? 'datetime-local' : 'date'}
      className={inputClasses}
      value={typeof value === 'string' ? value : ''}
      disabled={disabled || field.disabled || field.readonly}
      readOnly={field.readonly === true}
      required={field.required === true}
      aria-describedby={describedBy}
      onChange={(e) => onChange(e.target.value === '' ? null : e.target.value)}
    />
  );
}

function FileInput({
  field,
  onChange,
  disabled,
  inputId,
  describedBy,
}: NativeProps & { field: FieldSchema }) {
  return (
    <input
      id={inputId}
      type="file"
      className="text-sm"
      disabled={disabled || field.disabled || field.readonly}
      aria-describedby={describedBy}
      onChange={(e) => onChange(e.target.files?.[0] ?? null)}
    />
  );
}

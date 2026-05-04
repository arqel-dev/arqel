/**
 * `<SelectInput>` — single-value select.
 *
 * Phase 1 ships the native `<select>` for predictability and
 * a11y-by-default. A searchable Combobox (Base UI) variant lands in
 * FIELDS-JS-003 follow-up when `props.searchable === true`.
 */

import type { SelectFieldSchema } from '@arqel-dev/types/fields';
import type { FieldRendererProps } from '@arqel-dev/ui/form';
import { normaliseOptions } from '../shared/options.js';
import { inputClasses } from '../shared/styles.js';

export function SelectInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const f = field as SelectFieldSchema;
  const hasError = errors !== undefined && errors.length > 0;
  const options = normaliseOptions(f.props.options);

  return (
    <select
      id={inputId}
      className={inputClasses}
      value={value === null || value === undefined ? '' : String(value)}
      disabled={disabled || f.disabled || f.readonly}
      required={f.required === true}
      aria-invalid={hasError || undefined}
      aria-describedby={describedBy}
      onChange={(e) => onChange(e.target.value === '' ? null : e.target.value)}
    >
      {(f.placeholder || !f.required) && <option value="">{f.placeholder ?? '—'}</option>}
      {options.map((opt) => (
        <option key={opt.value} value={opt.value}>
          {opt.label}
        </option>
      ))}
    </select>
  );
}

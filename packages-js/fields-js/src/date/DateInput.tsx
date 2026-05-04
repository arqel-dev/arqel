/**
 * `<DateInput>` — native `<input type="date">`.
 *
 * Phase 1 leans on the browser's built-in date picker for footprint
 * reasons. A `react-day-picker`-based custom picker lands in Phase 2
 * when we need locale-specific formatting + min/max enforcement
 * beyond what the native control provides.
 */

import type { DateFieldSchema } from '@arqel-dev/types/fields';
import type { FieldRendererProps } from '@arqel-dev/ui/form';
import { inputClasses } from '../shared/styles.js';

export function DateInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const f = field as DateFieldSchema;
  const hasError = errors !== undefined && errors.length > 0;
  return (
    <input
      id={inputId}
      type="date"
      className={inputClasses}
      value={typeof value === 'string' ? value : ''}
      disabled={disabled || f.disabled || f.readonly}
      readOnly={f.readonly === true}
      required={f.required === true}
      min={f.props.minDate}
      max={f.props.maxDate}
      aria-invalid={hasError || undefined}
      aria-describedby={describedBy}
      onChange={(e) => onChange(e.target.value === '' ? null : e.target.value)}
    />
  );
}

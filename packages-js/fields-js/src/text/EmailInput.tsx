/**
 * `<EmailInput>` — `<input type="email">` with sensible defaults.
 *
 * Mirrors browser email validation; server-side validation remains the
 * source of truth (Laravel `email` rule).
 */

import type { EmailFieldSchema } from '@arqel-dev/types/fields';
import type { FieldRendererProps } from '@arqel-dev/ui/form';
import { inputClasses } from '../shared/styles.js';

export function EmailInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const f = field as EmailFieldSchema;
  const hasError = errors !== undefined && errors.length > 0;
  return (
    <input
      id={inputId}
      type="email"
      className={inputClasses}
      value={typeof value === 'string' ? value : ''}
      placeholder={f.placeholder ?? undefined}
      disabled={disabled || f.disabled || f.readonly}
      readOnly={f.readonly === true}
      required={f.required === true}
      autoComplete={f.props.autocomplete ?? 'email'}
      aria-invalid={hasError || undefined}
      aria-describedby={describedBy}
      onChange={(e) => onChange(e.target.value)}
    />
  );
}

/**
 * `<TextInput>` — generic single-line text field.
 *
 * Used as the registered component for FieldType `text` and `slug`.
 * Slug-specific auto-generation logic lives in FIELDS-JS-004's
 * `<SlugInput>`; this component is the simple text path.
 */

import type { TextFieldSchema } from '@arqel-dev/types/fields';
import type { FieldRendererProps } from '@arqel-dev/ui/form';
import { inputClasses } from '../shared/styles.js';

export function TextInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const f = field as TextFieldSchema;
  const hasError = errors !== undefined && errors.length > 0;
  return (
    <input
      id={inputId}
      type="text"
      className={inputClasses}
      value={typeof value === 'string' ? value : ''}
      placeholder={f.placeholder ?? undefined}
      disabled={disabled || f.disabled || f.readonly}
      readOnly={f.readonly === true}
      required={f.required === true}
      maxLength={f.props.maxLength}
      minLength={f.props.minLength}
      pattern={f.props.pattern}
      autoComplete={f.props.autocomplete}
      aria-invalid={hasError || undefined}
      aria-describedby={describedBy}
      onChange={(e) => onChange(e.target.value)}
    />
  );
}

import type { DateTimeFieldSchema } from '@arqel/types/fields';
import type { FieldRendererProps } from '@arqel/ui/form';
import { inputClasses } from '../shared/styles.js';

export function DateTimeInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const f = field as DateTimeFieldSchema;
  const hasError = errors !== undefined && errors.length > 0;
  return (
    <input
      id={inputId}
      type="datetime-local"
      className={inputClasses}
      value={typeof value === 'string' ? value : ''}
      disabled={disabled || f.disabled || f.readonly}
      readOnly={f.readonly === true}
      required={f.required === true}
      min={f.props.minDate}
      max={f.props.maxDate}
      step={f.props.seconds ? 1 : undefined}
      aria-invalid={hasError || undefined}
      aria-describedby={describedBy}
      onChange={(e) => onChange(e.target.value === '' ? null : e.target.value)}
    />
  );
}

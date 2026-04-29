import type { BooleanFieldSchema } from '@arqel/types/fields';
import type { FieldRendererProps } from '@arqel/ui/form';
import { checkboxClasses } from '../shared/styles.js';

export function Checkbox({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const f = field as BooleanFieldSchema;
  const hasError = errors !== undefined && errors.length > 0;
  return (
    <input
      id={inputId}
      type="checkbox"
      className={checkboxClasses}
      checked={value === true}
      disabled={disabled || f.disabled || f.readonly}
      aria-invalid={hasError || undefined}
      aria-describedby={describedBy}
      onChange={(e) => onChange(e.target.checked)}
    />
  );
}

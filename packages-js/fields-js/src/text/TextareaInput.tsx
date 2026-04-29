import type { TextareaFieldSchema } from '@arqel/types/fields';
import type { FieldRendererProps } from '@arqel/ui/form';
import { cn } from '@arqel/ui/utils';
import { inputClasses } from '../shared/styles.js';

export function TextareaInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const f = field as TextareaFieldSchema;
  const hasError = errors !== undefined && errors.length > 0;
  return (
    <textarea
      id={inputId}
      className={cn(inputClasses, 'h-auto min-h-[5rem] py-2')}
      value={typeof value === 'string' ? value : ''}
      placeholder={f.placeholder ?? undefined}
      disabled={disabled || f.disabled || f.readonly}
      readOnly={f.readonly === true}
      required={f.required === true}
      rows={4}
      aria-invalid={hasError || undefined}
      aria-describedby={describedBy}
      onChange={(e) => onChange(e.target.value)}
    />
  );
}

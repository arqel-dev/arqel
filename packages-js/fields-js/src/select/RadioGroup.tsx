/**
 * `<RadioGroup>` — radio buttons in a `role="radiogroup"`.
 *
 * Honours `props.inline` to lay items out horizontally vs the default
 * stacked layout.
 */

import type { RadioFieldSchema } from '@arqel/types/fields';
import type { FieldRendererProps } from '@arqel/ui/form';
import { cn } from '@arqel/ui/utils';

export function RadioGroup({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const f = field as RadioFieldSchema;
  const hasError = errors !== undefined && errors.length > 0;
  const isDisabled = disabled || f.disabled || f.readonly;

  return (
    <div
      id={inputId}
      role="radiogroup"
      aria-invalid={hasError || undefined}
      aria-describedby={describedBy}
      className={cn('flex gap-3', f.props.inline ? 'flex-row flex-wrap' : 'flex-col')}
    >
      {(f.props.options ?? []).map((opt) => {
        const checked = String(value ?? '') === String(opt.value);
        return (
          <label key={String(opt.value)} className="inline-flex items-center gap-2 text-sm">
            <input
              type="radio"
              name={f.name}
              value={String(opt.value)}
              checked={checked}
              disabled={isDisabled}
              onChange={() => onChange(opt.value)}
              className="h-4 w-4 border-[var(--color-arqel-input)] text-[var(--color-arqel-primary)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)]"
            />
            {opt.label}
          </label>
        );
      })}
    </div>
  );
}

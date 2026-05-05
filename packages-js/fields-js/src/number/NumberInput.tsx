/**
 * `<NumberInput>` — numeric input with optional stepper buttons.
 *
 * Empty string is normalised to `null`; non-numeric inputs are also
 * sent as `null` so server-side validation rules see typed values.
 */

import type { NumberFieldSchema } from '@arqel-dev/types/fields';
import type { FieldRendererProps } from '@arqel-dev/ui/form';
import { cn } from '@arqel-dev/ui/utils';
import { inputClasses } from '../shared/styles.js';

export function NumberInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const f = field as NumberFieldSchema;
  const hasError = errors !== undefined && errors.length > 0;
  const isDisabled = disabled || f.disabled || f.readonly;

  const setValue = (raw: number) => {
    if (Number.isNaN(raw)) {
      onChange(null);
      return;
    }
    if (f.props.integer) onChange(Math.trunc(raw));
    else onChange(raw);
  };

  const current = typeof value === 'number' ? value : null;
  const step = typeof f.props.step === 'number' ? f.props.step : 1;

  return (
    <div className="relative inline-flex w-full">
      <input
        id={inputId}
        type="number"
        inputMode={f.props.integer ? 'numeric' : 'decimal'}
        className={cn(inputClasses, 'pr-16')}
        value={current === null ? '' : String(current)}
        placeholder={f.placeholder ?? undefined}
        disabled={isDisabled}
        readOnly={f.readonly === true}
        required={f.required === true}
        min={f.props.min}
        max={f.props.max}
        step={f.props.step}
        aria-invalid={hasError || undefined}
        aria-describedby={describedBy}
        onChange={(e) => {
          if (e.target.value === '') {
            onChange(null);
            return;
          }
          setValue(Number(e.target.value));
        }}
      />
      <div className="absolute inset-y-0 right-0 flex flex-col">
        <button
          type="button"
          aria-label="Increment"
          disabled={isDisabled}
          onClick={() => setValue((current ?? 0) + step)}
          className="flex h-1/2 w-8 items-center justify-center text-xs text-muted-foreground hover:text-foreground disabled:opacity-50"
        >
          ▲
        </button>
        <button
          type="button"
          aria-label="Decrement"
          disabled={isDisabled}
          onClick={() => setValue((current ?? 0) - step)}
          className="flex h-1/2 w-8 items-center justify-center text-xs text-muted-foreground hover:text-foreground disabled:opacity-50"
        >
          ▼
        </button>
      </div>
    </div>
  );
}

/**
 * `<MultiSelectInput>` — chip-list multi-select.
 *
 * Selected items render as removable chips above the option dropdown.
 * Backed by a native `<select multiple>` for a11y; the chip strip is
 * presentation-only and writes back through the same `onChange`.
 */

import type { MultiSelectFieldSchema } from '@arqel-dev/types/fields';
import type { FieldRendererProps } from '@arqel-dev/ui/form';
import { cn } from '@arqel-dev/ui/utils';
import { normaliseOptions } from '../shared/options.js';
import { inputClasses } from '../shared/styles.js';

export function MultiSelectInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const f = field as MultiSelectFieldSchema;
  const hasError = errors !== undefined && errors.length > 0;
  const options = normaliseOptions(f.props.options);
  const arr = Array.isArray(value) ? (value as Array<string | number>) : [];
  const selectedSet = new Set(arr.map(String));
  const isDisabled = disabled || f.disabled || f.readonly;

  const remove = (v: string | number) => {
    onChange(arr.filter((x) => String(x) !== String(v)));
  };

  return (
    <div className="flex flex-col gap-2">
      {arr.length > 0 && (
        <div className="flex flex-wrap gap-1">
          {arr.map((v) => {
            const opt = options.find((o) => String(o.value) === String(v));
            return (
              <span
                key={String(v)}
                className="inline-flex items-center gap-1 rounded-sm bg-muted px-2 py-0.5 text-xs"
              >
                {opt?.label ?? String(v)}
                {!isDisabled && (
                  <button
                    type="button"
                    aria-label={`Remove ${opt?.label ?? String(v)}`}
                    onClick={() => remove(v)}
                    className="text-muted-foreground hover:text-foreground"
                  >
                    ✕
                  </button>
                )}
              </span>
            );
          })}
        </div>
      )}
      <select
        id={inputId}
        multiple
        className={cn(inputClasses, 'h-auto min-h-[6rem] py-1')}
        value={Array.from(selectedSet)}
        disabled={isDisabled}
        aria-invalid={hasError || undefined}
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
    </div>
  );
}

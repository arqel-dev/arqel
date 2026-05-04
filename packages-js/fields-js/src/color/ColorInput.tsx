/**
 * `<ColorInput>` — native color picker plus optional preset swatches.
 *
 * `field.props.presets: string[]` (hex strings) renders a strip of
 * clickable swatches above the picker. The native `<input type="color">`
 * is a/11y-friendly out of the box.
 */

import type { ColorFieldSchema } from '@arqel-dev/types/fields';
import type { FieldRendererProps } from '@arqel-dev/ui/form';
import { cn } from '@arqel-dev/ui/utils';

export function ColorInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const f = field as ColorFieldSchema;
  const hasError = errors !== undefined && errors.length > 0;
  const isDisabled = disabled || f.disabled || f.readonly;
  const current = typeof value === 'string' && value.length > 0 ? value : '#000000';
  const presets = f.props.presets ?? [];

  return (
    <div className="flex flex-wrap items-center gap-2">
      <input
        id={inputId}
        type="color"
        className={cn(
          'h-9 w-12 cursor-pointer rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-input)]',
          'disabled:cursor-not-allowed disabled:opacity-50',
        )}
        value={current}
        disabled={isDisabled}
        aria-invalid={hasError || undefined}
        aria-describedby={describedBy}
        onChange={(e) => onChange(e.target.value)}
      />
      <input
        type="text"
        className="h-9 w-28 rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-input)] bg-[var(--color-arqel-bg)] px-2 font-mono text-xs"
        value={current}
        disabled={isDisabled}
        onChange={(e) => onChange(e.target.value)}
      />
      {presets.length > 0 && (
        <div className="flex flex-wrap gap-1">
          {presets.map((preset) => (
            <button
              key={preset}
              type="button"
              aria-label={`Preset ${preset}`}
              disabled={isDisabled}
              onClick={() => onChange(preset)}
              className="h-6 w-6 rounded-full border border-[var(--color-arqel-border)] disabled:cursor-not-allowed disabled:opacity-50"
              style={{ backgroundColor: preset }}
            />
          ))}
        </div>
      )}
    </div>
  );
}

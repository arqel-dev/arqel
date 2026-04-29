/**
 * `<Toggle>` — switch-style boolean control with optional on/off labels.
 *
 * Renders as a `role="switch"` button so screen readers announce it
 * correctly; styling is the canonical iOS-style track/thumb.
 */

import type { ToggleFieldSchema } from '@arqel/types/fields';
import type { FieldRendererProps } from '@arqel/ui/form';
import { cn } from '@arqel/ui/utils';

export function Toggle({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const f = field as ToggleFieldSchema;
  const checked = value === true;
  const hasError = errors !== undefined && errors.length > 0;
  const isDisabled = disabled || f.disabled || f.readonly;

  return (
    <div className="flex items-center gap-3">
      <button
        id={inputId}
        type="button"
        role="switch"
        aria-checked={checked}
        aria-invalid={hasError || undefined}
        aria-describedby={describedBy}
        disabled={isDisabled}
        onClick={() => onChange(!checked)}
        className={cn(
          'relative inline-flex h-6 w-11 items-center rounded-full transition-colors',
          checked ? 'bg-[var(--color-arqel-primary)]' : 'bg-[var(--color-arqel-muted)]',
          'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)]',
          'disabled:cursor-not-allowed disabled:opacity-50',
        )}
      >
        <span
          aria-hidden="true"
          className={cn(
            'inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform',
            checked ? 'translate-x-5' : 'translate-x-0.5',
          )}
        />
      </button>
      {(f.props.onLabel || f.props.offLabel) && (
        <span className="text-sm text-[var(--color-arqel-muted-fg)]">
          {checked ? f.props.onLabel : f.props.offLabel}
        </span>
      )}
    </div>
  );
}

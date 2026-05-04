/**
 * `<PasswordInput>` — masked input with a "reveal" toggle button.
 *
 * Toggles between `type="password"` and `type="text"`; the button
 * is rendered inside the trailing edge of the input via wrapper.
 */

import type { PasswordFieldSchema } from '@arqel-dev/types/fields';
import type { FieldRendererProps } from '@arqel-dev/ui/form';
import { cn } from '@arqel-dev/ui/utils';
import { useState } from 'react';
import { inputClasses } from '../shared/styles.js';

export function PasswordInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const f = field as PasswordFieldSchema;
  const [revealed, setRevealed] = useState(false);
  const hasError = errors !== undefined && errors.length > 0;
  const isDisabled = disabled || f.disabled || f.readonly;

  return (
    <div className="relative">
      <input
        id={inputId}
        type={revealed ? 'text' : 'password'}
        className={cn(inputClasses, 'pr-10')}
        value={typeof value === 'string' ? value : ''}
        placeholder={f.placeholder ?? undefined}
        disabled={isDisabled}
        readOnly={f.readonly === true}
        required={f.required === true}
        autoComplete={f.props.autocomplete ?? 'current-password'}
        aria-invalid={hasError || undefined}
        aria-describedby={describedBy}
        onChange={(e) => onChange(e.target.value)}
      />
      <button
        type="button"
        aria-label={revealed ? 'Hide password' : 'Show password'}
        aria-pressed={revealed}
        disabled={isDisabled}
        onClick={() => setRevealed((v) => !v)}
        className={cn(
          'absolute inset-y-0 right-0 flex w-9 items-center justify-center text-sm',
          'text-[var(--color-arqel-muted-fg)] hover:text-[var(--color-arqel-fg)]',
          'disabled:cursor-not-allowed disabled:opacity-50',
        )}
      >
        {revealed ? '◉' : '○'}
      </button>
    </div>
  );
}

/**
 * `<FieldRenderer>` — renders a single field.
 *
 * Resolution order:
 *   1. Component registered via `registerField(field.component)` (rich
 *      inputs from `@arqel/fields`)
 *   2. Native fallback dispatched by `field.type` (text/number/boolean/...)
 *
 * Errors come from `errors[name]` (Inertia or client). The component
 * also honours `disabled` / `readonly` / `required` from the schema.
 */

import type { FieldSchema } from '@arqel/types/fields';
import { type ChangeEvent, type ReactNode, useId } from 'react';
import { cn } from '../utils/cn.js';
import { getFieldComponent } from './FieldRegistry.js';
import { NativeFieldInput } from './nativeFields.js';

export interface FieldRendererProps {
  field: FieldSchema;
  value: unknown;
  onChange: (value: unknown) => void;
  errors?: string[] | undefined;
  disabled?: boolean | undefined;
  className?: string | undefined;
  inputId?: string | undefined;
  describedBy?: string | undefined;
}

export function FieldRenderer(props: FieldRendererProps): ReactNode {
  const { field, errors, className } = props;
  const id = useId();
  const errorId = `${id}-error`;
  const helperId = `${id}-helper`;
  const hasError = errors && errors.length > 0;

  const Custom = field.component ? getFieldComponent(field.component) : undefined;
  const Input = Custom ?? (NativeFieldInput as typeof NativeFieldInput);

  const describedBy = [field.helperText ? helperId : null, hasError ? errorId : null]
    .filter(Boolean)
    .join(' ');

  return (
    <div
      data-arqel-field={field.name}
      data-field-type={field.type}
      className={cn('flex flex-col gap-1', className)}
    >
      {field.label !== null && field.label !== '' && (
        <label htmlFor={id} className="text-sm font-medium">
          {field.label}
          {field.required && (
            <span aria-hidden="true" className="ml-0.5 text-[var(--color-arqel-destructive)]">
              *
            </span>
          )}
        </label>
      )}
      <Input {...props} inputId={id} describedBy={describedBy || undefined} />
      {field.helperText && !hasError && (
        <p id={helperId} className="text-xs text-[var(--color-arqel-muted-fg)]">
          {field.helperText}
        </p>
      )}
      {hasError && (
        <p id={errorId} role="alert" className="text-xs text-[var(--color-arqel-destructive)]">
          {errors[0]}
        </p>
      )}
    </div>
  );
}

/* ─── helpers ────────────────────────────────────────────────────── */

export function asInputChange<T>(
  cast: (raw: string) => T,
): (event: ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => T {
  return (event) => cast(event.target.value);
}

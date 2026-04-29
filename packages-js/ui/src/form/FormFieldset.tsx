/**
 * `<FormFieldset>` — semantic `<fieldset>` with grid body.
 */

import type { FieldsetProps } from '@arqel/types/forms';
import type { ReactNode } from 'react';
import { cn } from '../utils/cn.js';

export interface FormFieldsetProps {
  config: FieldsetProps;
  children: ReactNode;
  className?: string;
}

export function FormFieldset({ config, children, className }: FormFieldsetProps) {
  return (
    <fieldset
      className={cn(
        'rounded-[var(--radius-arqel)] border border-[var(--color-arqel-border)] p-4',
        className,
      )}
    >
      <legend className="px-1 text-sm font-medium">{config.legend}</legend>
      <div
        className="grid gap-4"
        style={{ gridTemplateColumns: `repeat(${config.columns ?? 1}, minmax(0, 1fr))` }}
      >
        {children}
      </div>
    </fieldset>
  );
}

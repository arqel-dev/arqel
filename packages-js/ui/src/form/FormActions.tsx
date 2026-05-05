/**
 * `<FormActions>` — submit + cancel + extra slots.
 *
 * Sits at the bottom of a form. The submit button is `type="submit"`
 * so wrapping a `<form>` around `<FormRenderer>` + `<FormActions>`
 * triggers the form's onSubmit naturally.
 */

import type { ReactNode } from 'react';
import { Button } from '../action/Button.js';
import { cn } from '../utils/cn.js';

export interface FormActionsProps {
  submitLabel?: string;
  cancelLabel?: string;
  onCancel?: () => void;
  processing?: boolean;
  disabled?: boolean;
  extra?: ReactNode;
  className?: string;
}

export function FormActions({
  submitLabel = 'Save',
  cancelLabel = 'Cancel',
  onCancel,
  processing = false,
  disabled = false,
  extra,
  className,
}: FormActionsProps) {
  return (
    <div
      className={cn(
        'flex flex-wrap items-center justify-end gap-2 border-t border-border pt-4',
        className,
      )}
    >
      {extra && <div className="mr-auto">{extra}</div>}
      {onCancel && (
        <Button type="button" variant="ghost" onClick={onCancel} disabled={processing}>
          {cancelLabel}
        </Button>
      )}
      <Button type="submit" disabled={processing || disabled}>
        {processing ? 'Saving…' : submitLabel}
      </Button>
    </div>
  );
}

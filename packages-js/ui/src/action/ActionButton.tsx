/**
 * `<ActionButton>` — renders an `ActionSchema` as a button.
 *
 * Behaviour matrix:
 *   - `requiresConfirmation` only → opens ConfirmDialog
 *   - `form` only                  → opens ActionFormModal
 *   - both                         → ConfirmDialog first, then form
 *   - neither                      → fires `onInvoke` immediately
 *
 * `onInvoke` receives optional form data so parents (typically using
 * `useAction()` from @arqel-dev/hooks) can dispatch the underlying request.
 */

import type { ActionColor, ActionSchema, ActionVariant } from '@arqel-dev/types/actions';
import type { FieldSchema } from '@arqel-dev/types/fields';
import { useState } from 'react';
import { ActionFormModal } from './ActionFormModal.js';
import { Button, type ButtonProps } from './Button.js';
import { ConfirmDialog } from './ConfirmDialog.js';

export interface ActionButtonProps {
  action: ActionSchema;
  /**
   * Field schemas for the form modal (resolved server-side from
   * `Action::form` via `FieldSchemaSerializer`). Optional override — when
   * omitted the component reads `action.formFields`, the canonical payload
   * shipped by `Action::toArray()` (#213).
   */
  formFields?: FieldSchema[] | undefined;
  onInvoke: (formValues?: Record<string, unknown>) => void;
  processing?: boolean;
  errors?: Record<string, string[]>;
  size?: ButtonProps['size'];
  className?: string;
}

const COLOR_TO_VARIANT: Record<ActionColor, ButtonProps['variant']> = {
  primary: 'default',
  secondary: 'outline',
  destructive: 'destructive',
  success: 'default',
  warning: 'default',
  info: 'default',
};

const VARIANT_OVERRIDE: Partial<Record<ActionVariant, ButtonProps['variant']>> = {
  default: 'default',
  outline: 'outline',
  ghost: 'ghost',
  destructive: 'destructive',
};

export function ActionButton({
  action,
  formFields,
  onInvoke,
  processing = false,
  errors,
  size = 'default',
  className,
}: ActionButtonProps) {
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [formOpen, setFormOpen] = useState(false);

  // Prefer an explicit prop; otherwise read the schema shipped by
  // `Action::toArray()` so the modal renders real inputs (#213).
  const resolvedFormFields = formFields ?? action.formFields ?? [];

  const variant: ButtonProps['variant'] =
    VARIANT_OVERRIDE[action.variant] ?? COLOR_TO_VARIANT[action.color];

  const handleClick = () => {
    if (action.disabled) return;
    if (action.requiresConfirmation) {
      setConfirmOpen(true);
    } else if (action.form && action.form.length > 0) {
      setFormOpen(true);
    } else {
      onInvoke();
    }
  };

  const handleConfirm = () => {
    setConfirmOpen(false);
    if (action.form && action.form.length > 0) {
      setFormOpen(true);
    } else {
      onInvoke();
    }
  };

  const handleFormSubmit = (values: Record<string, unknown>) => {
    onInvoke(values);
    setFormOpen(false);
  };

  return (
    <>
      <Button
        variant={variant}
        size={size}
        disabled={action.disabled === true || processing}
        title={action.tooltip}
        onClick={handleClick}
        className={className}
      >
        {action.label}
      </Button>
      {action.requiresConfirmation && (
        <ConfirmDialog
          open={confirmOpen}
          onOpenChange={setConfirmOpen}
          config={action.confirmation}
          onConfirm={handleConfirm}
          processing={processing}
        />
      )}
      {action.form && action.form.length > 0 && (
        <ActionFormModal
          open={formOpen}
          onOpenChange={setFormOpen}
          action={action}
          fields={resolvedFormFields}
          onSubmit={handleFormSubmit}
          processing={processing}
          errors={errors}
        />
      )}
    </>
  );
}

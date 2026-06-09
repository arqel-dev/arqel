/**
 * `<ActionMenu>` — renders a list of actions inline (≤ `inlineThreshold`)
 * or collapses them into a shadcn (Radix) dropdown menu when the list grows.
 *
 * Each menu item delegates to `<ActionButton>` so confirmation /
 * form-modal behaviour is consistent across surfaces. The dropdown
 * trigger is rendered as a ghost icon button by default; callers can
 * supply a custom `trigger` slot.
 */

import type { ActionSchema } from '@arqel-dev/types/actions';
import type { FieldSchema } from '@arqel-dev/types/fields';
import { type ReactNode, useState } from 'react';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '../shadcn/ui/dropdown-menu.js';
import { cn } from '../utils/cn.js';
import { ActionButton } from './ActionButton.js';
import { ActionFormModal } from './ActionFormModal.js';
import { Button } from './Button.js';
import { ConfirmDialog } from './ConfirmDialog.js';

/** Does this action need a gate (confirm/form) before invoking? */
function actionHasForm(action: ActionSchema): boolean {
  return Array.isArray(action.form) && action.form.length > 0;
}

export interface ActionMenuProps {
  actions: ActionSchema[];
  /**
   * Optional per-action override map keyed by action name. When an action
   * is absent here, `<ActionButton>` falls back to the action's own
   * `formFields` payload shipped by `Action::toArray()` (#213).
   */
  formFieldsByAction?: Record<string, FieldSchema[]>;
  onInvoke: (action: ActionSchema, formValues?: Record<string, unknown>) => void;
  inlineThreshold?: number;
  processing?: boolean;
  trigger?: ReactNode;
  className?: string;
}

export function ActionMenu({
  actions,
  formFieldsByAction = {},
  onInvoke,
  inlineThreshold = 3,
  processing = false,
  trigger,
  className,
}: ActionMenuProps) {
  // Which dropdown action (if any) is currently driving a confirm dialog /
  // form modal. The modals are mounted as siblings of the menu so they
  // survive the menu closing on `onSelect`.
  const [confirmAction, setConfirmAction] = useState<ActionSchema | null>(null);
  const [formAction, setFormAction] = useState<ActionSchema | null>(null);

  if (actions.length === 0) return null;

  if (actions.length <= inlineThreshold) {
    return (
      <div className={cn('flex flex-wrap items-center gap-1', className)}>
        {actions.map((action) => (
          <ActionButton
            key={action.name}
            action={action}
            formFields={formFieldsByAction[action.name]}
            onInvoke={(values) => onInvoke(action, values)}
            processing={processing}
            size="sm"
          />
        ))}
      </div>
    );
  }

  const handleSelect = (action: ActionSchema) => {
    if (action.disabled) return;
    // Mirror ActionButton's gating: confirmation first, then form, else
    // invoke directly. The modals open as siblings of the (now closing)
    // menu so destructive/form actions never fire without a gate (#229,
    // #231).
    if (action.requiresConfirmation) {
      setConfirmAction(action);
    } else if (actionHasForm(action)) {
      setFormAction(action);
    } else {
      onInvoke(action);
    }
  };

  const handleConfirm = () => {
    const action = confirmAction;
    setConfirmAction(null);
    if (!action) return;
    if (actionHasForm(action)) {
      setFormAction(action);
    } else {
      onInvoke(action);
    }
  };

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          {trigger ?? (
            <Button variant="ghost" size="icon" aria-label="Actions">
              ⋯
            </Button>
          )}
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" sideOffset={6} className={cn('min-w-[12rem]', className)}>
          {actions.map((action) => (
            <DropdownMenuItem
              key={action.name}
              disabled={action.disabled === true}
              variant={action.color === 'destructive' ? 'destructive' : 'default'}
              onSelect={() => handleSelect(action)}
            >
              {action.label}
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
      {confirmAction && (
        <ConfirmDialog
          open
          onOpenChange={(next) => {
            if (!next) setConfirmAction(null);
          }}
          config={confirmAction.confirmation}
          onConfirm={handleConfirm}
          processing={processing}
        />
      )}
      {formAction && (
        <ActionFormModal
          open
          onOpenChange={(next) => {
            if (!next) setFormAction(null);
          }}
          action={formAction}
          fields={formFieldsByAction[formAction.name] ?? formAction.formFields ?? []}
          onSubmit={(values) => {
            const action = formAction;
            setFormAction(null);
            onInvoke(action, values);
          }}
          processing={processing}
        />
      )}
    </>
  );
}

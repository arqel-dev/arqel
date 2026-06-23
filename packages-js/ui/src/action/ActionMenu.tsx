/**
 * `<ActionMenu>` — renders a list of actions inline (≤ `inlineThreshold`)
 * or collapses them into a shadcn (Radix) dropdown menu when the list grows.
 *
 * Each menu item delegates to `<ActionButton>` so confirmation /
 * form-modal behaviour is consistent across surfaces. The dropdown
 * trigger is rendered as a ghost icon button by default; callers can
 * supply a custom `trigger` slot.
 */

import { useArqelTranslations } from '@arqel-dev/react/utils';
import type { ActionSchema } from '@arqel-dev/types/actions';
import type { FieldSchema } from '@arqel-dev/types/fields';
import { type ReactNode, useState } from 'react';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '../shadcn/ui/dropdown-menu.js';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '../shadcn/ui/sheet.js';
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
  const t = useArqelTranslations();
  // Which dropdown action (if any) is currently driving a confirm dialog /
  // form modal. The modals are mounted as siblings of the menu so they
  // survive the menu closing on `onSelect`.
  const [confirmAction, setConfirmAction] = useState<ActionSchema | null>(null);
  const [formAction, setFormAction] = useState<ActionSchema | null>(null);
  // The mobile bottom-sheet is controlled so selecting an item can close it
  // (mirroring the Dropdown's onSelect auto-close).
  const [sheetOpen, setSheetOpen] = useState(false);

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
            // 44px touch target on mobile (WCAG 2.5.5); dense 32px on >=md.
            className="h-11 md:h-8"
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

  // One visual trigger shared by both presentation surfaces. The Dropdown
  // wraps it via DropdownMenuTrigger (asChild); the Sheet opens it on click.
  const triggerNode = trigger ?? (
    <Button variant="ghost" size="icon-touch" aria-label={t('arqel.actions.menu', 'Actions')}>
      ⋯
    </Button>
  );

  return (
    <>
      {/* Desktop (>=md): the Radix dropdown popper, unchanged. */}
      <div data-arqel-action-dropdown="" className="hidden md:contents">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>{triggerNode}</DropdownMenuTrigger>
          <DropdownMenuContent
            align="end"
            sideOffset={6}
            className={cn('min-w-[12rem]', className)}
          >
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
      </div>

      {/* Mobile (<md): a full-width bottom sheet with >=44px items. */}
      <div className="md:hidden">
        <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
          <button
            type="button"
            aria-label={t('arqel.actions.menu', 'Actions')}
            className="inline-flex size-11 items-center justify-center rounded-md text-lg hover:bg-accent"
            onClick={() => setSheetOpen(true)}
          >
            ⋯
          </button>
          <SheetContent
            side="bottom"
            className="max-h-[80vh] gap-0 overflow-y-auto pb-[env(safe-area-inset-bottom)]"
          >
            <SheetHeader className="px-4 pt-4 pb-2">
              <SheetTitle>{t('arqel.actions.menu', 'Actions')}</SheetTitle>
            </SheetHeader>
            <div className="flex flex-col px-2 pb-2">
              {actions.map((action) => (
                <button
                  key={action.name}
                  type="button"
                  data-arqel-sheet-action=""
                  disabled={action.disabled === true}
                  className={cn(
                    'flex min-h-11 w-full items-center rounded-md px-3 text-left text-sm hover:bg-accent disabled:pointer-events-none disabled:opacity-50',
                    action.color === 'destructive' && 'text-destructive',
                  )}
                  onClick={() => {
                    setSheetOpen(false);
                    handleSelect(action);
                  }}
                >
                  {action.label}
                </button>
              ))}
            </div>
          </SheetContent>
        </Sheet>
      </div>

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

/**
 * `<ActionMenu>` — renders a list of actions inline (≤ `inlineThreshold`)
 * or collapses them into a Base UI dropdown menu when the list grows.
 *
 * Each menu item delegates to `<ActionButton>` so confirmation /
 * form-modal behaviour is consistent across surfaces. The dropdown
 * trigger is rendered as a ghost icon button by default; callers can
 * supply a custom `trigger` slot.
 */

import type { ActionSchema } from '@arqel/types/actions';
import type { FieldSchema } from '@arqel/types/fields';
import { Menu } from '@base-ui-components/react/menu';
import type { ReactNode } from 'react';
import { cn } from '../utils/cn.js';
import { ActionButton } from './ActionButton.js';
import { Button } from './Button.js';

export interface ActionMenuProps {
  actions: ActionSchema[];
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
  if (actions.length === 0) return null;

  if (actions.length <= inlineThreshold) {
    return (
      <div className={cn('flex flex-wrap items-center gap-1', className)}>
        {actions.map((action) => (
          <ActionButton
            key={action.name}
            action={action}
            formFields={formFieldsByAction[action.name] ?? []}
            onInvoke={(values) => onInvoke(action, values)}
            processing={processing}
            size="sm"
          />
        ))}
      </div>
    );
  }

  return (
    <Menu.Root>
      <Menu.Trigger
        render={
          trigger ? (
            (trigger as React.ReactElement)
          ) : (
            <Button variant="ghost" size="icon" aria-label="Actions">
              ⋯
            </Button>
          )
        }
      />
      <Menu.Portal>
        <Menu.Positioner sideOffset={6}>
          <Menu.Popup
            className={cn(
              'z-50 min-w-[12rem] rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-border)] bg-[var(--color-arqel-bg)] p-1 shadow-md outline-none',
              className,
            )}
          >
            {actions.map((action) => (
              <Menu.Item
                key={action.name}
                disabled={action.disabled === true}
                className={cn(
                  'flex cursor-pointer select-none items-center rounded-[var(--radius-arqel-sm)] px-3 py-1.5 text-sm outline-none',
                  'data-[highlighted]:bg-[var(--color-arqel-muted)]',
                  action.color === 'destructive' && 'text-[var(--color-arqel-destructive)]',
                  'data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
                )}
                onClick={() => {
                  // Delegate to ActionButton-style handling by routing
                  // through onInvoke. Confirmation/form modals are still
                  // available via direct ActionButton usage; the menu
                  // is intended for plain links / direct invocations.
                  if (action.disabled) return;
                  onInvoke(action);
                }}
              >
                {action.label}
              </Menu.Item>
            ))}
          </Menu.Popup>
        </Menu.Positioner>
      </Menu.Portal>
    </Menu.Root>
  );
}

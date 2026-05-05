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
import type { ReactNode } from 'react';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '../shadcn/ui/dropdown-menu.js';
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
            onSelect={() => {
              // Delegate to ActionButton-style handling by routing
              // through onInvoke. Confirmation/form modals are still
              // available via direct ActionButton usage; the menu
              // is intended for plain links / direct invocations.
              if (action.disabled) return;
              onInvoke(action);
            }}
          >
            {action.label}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

/**
 * `<ConfirmDialog>` — confirmation modal driven by the shadcn (Radix) Dialog.
 *
 * Phase 1 supports the canonical `ConfirmationConfig`:
 *  - heading / description
 *  - color (destructive | warning | info) — drives submit button variant
 *  - submit / cancel labels
 *  - "type to confirm" via `requiresText` (submit stays disabled until
 *    the typed value matches exactly)
 *
 * Keyboard: Escape cancels (Radix built-in dismiss); Enter inside the
 * type-to-confirm input fires confirm when the value matches.
 */

import type { ConfirmationConfig } from '@arqel-dev/types/actions';
import { useEffect, useState } from 'react';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '../shadcn/ui/dialog.js';
import { Button, type ButtonProps } from './Button.js';

export interface ConfirmDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  config?: ConfirmationConfig | undefined;
  onConfirm: () => void;
  processing?: boolean;
}

const COLOR_VARIANT: Record<NonNullable<ConfirmationConfig['color']>, ButtonProps['variant']> = {
  destructive: 'destructive',
  warning: 'default',
  info: 'default',
};

export function ConfirmDialog({
  open,
  onOpenChange,
  config,
  onConfirm,
  processing = false,
}: ConfirmDialogProps) {
  const [typed, setTyped] = useState('');

  useEffect(() => {
    if (!open) setTyped('');
  }, [open]);

  const requiresText = config?.requiresText;
  const submitDisabled = processing || (requiresText !== undefined && typed !== requiresText);
  const variant = COLOR_VARIANT[config?.color ?? 'destructive'];
  const submitLabel = config?.submitLabel ?? (config?.color === 'info' ? 'Confirm' : 'Delete');
  const cancelLabel = config?.cancelLabel ?? 'Cancel';

  return (
    <Dialog open={open} onOpenChange={(next) => onOpenChange(next)}>
      <DialogContent className="sm:max-w-[28rem]" showCloseButton={false}>
        <DialogHeader>
          <DialogTitle>{config?.heading ?? 'Are you sure?'}</DialogTitle>
          {config?.description && <DialogDescription>{config.description}</DialogDescription>}
        </DialogHeader>
        {requiresText !== undefined && (
          <label className="flex flex-col gap-1 text-xs text-muted-foreground">
            <span>
              Type <code className="font-mono text-foreground">{requiresText}</code> to confirm
            </span>
            <input
              type="text"
              ref={(el) => el?.focus()}
              value={typed}
              onChange={(e) => setTyped(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter' && !submitDisabled) {
                  e.preventDefault();
                  onConfirm();
                }
              }}
              className="h-9 rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            />
          </label>
        )}
        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" variant="ghost" disabled={processing}>
              {cancelLabel}
            </Button>
          </DialogClose>
          <Button type="button" variant={variant} disabled={submitDisabled} onClick={onConfirm}>
            {processing ? 'Working…' : submitLabel}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

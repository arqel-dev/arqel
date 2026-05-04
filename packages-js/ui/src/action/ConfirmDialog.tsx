/**
 * `<ConfirmDialog>` — confirmation modal driven by a Base UI Dialog.
 *
 * Phase 1 supports the canonical `ConfirmationConfig`:
 *  - heading / description
 *  - color (destructive | warning | info) — drives submit button variant
 *  - submit / cancel labels
 *  - "type to confirm" via `requiresText` (submit stays disabled until
 *    the typed value matches exactly)
 *
 * Keyboard: Escape cancels (via Base UI's built-in dismiss); Enter
 * inside the type-to-confirm input fires confirm when the value matches.
 */

import type { ConfirmationConfig } from '@arqel-dev/types/actions';
import { Dialog } from '@base-ui-components/react/dialog';
import { useEffect, useState } from 'react';
import { cn } from '../utils/cn.js';
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
    <Dialog.Root open={open} onOpenChange={(next) => onOpenChange(next)} modal>
      <Dialog.Portal>
        <Dialog.Backdrop className="fixed inset-0 z-40 bg-black/40" />
        <Dialog.Popup
          className={cn(
            'fixed left-1/2 top-1/2 z-50 w-[28rem] max-w-[90vw] -translate-x-1/2 -translate-y-1/2',
            'rounded-[var(--radius-arqel)] border border-[var(--color-arqel-border)] bg-[var(--color-arqel-bg)] p-6 shadow-xl outline-none',
          )}
        >
          <Dialog.Title className="text-lg font-semibold">
            {config?.heading ?? 'Are you sure?'}
          </Dialog.Title>
          {config?.description && (
            <Dialog.Description className="mt-2 text-sm text-[var(--color-arqel-muted-fg)]">
              {config.description}
            </Dialog.Description>
          )}
          {requiresText !== undefined && (
            <label className="mt-4 flex flex-col gap-1 text-xs text-[var(--color-arqel-muted-fg)]">
              <span>
                Type <code className="font-mono text-[var(--color-arqel-fg)]">{requiresText}</code>{' '}
                to confirm
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
                className="h-9 rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-input)] bg-[var(--color-arqel-bg)] px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)]"
              />
            </label>
          )}
          <div className="mt-6 flex items-center justify-end gap-2">
            <Dialog.Close
              render={
                <Button type="button" variant="ghost" disabled={processing}>
                  {cancelLabel}
                </Button>
              }
            />
            <Button type="button" variant={variant} disabled={submitDisabled} onClick={onConfirm}>
              {processing ? 'Working…' : submitLabel}
            </Button>
          </div>
        </Dialog.Popup>
      </Dialog.Portal>
    </Dialog.Root>
  );
}

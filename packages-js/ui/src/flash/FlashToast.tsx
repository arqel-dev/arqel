/**
 * `<FlashToast>` — single auto-dismissing toast.
 *
 * Self-rendered (no `sonner` dep) so apps with strict bundle budgets
 * stay lean. Replaceable via FlashContainer's `renderToast` prop when
 * a richer library is preferred.
 */

import { useEffect } from 'react';
import { cn } from '../utils/cn.js';

export type FlashKind = 'success' | 'error' | 'info' | 'warning';

export interface FlashToastProps {
  kind: FlashKind;
  message: string;
  onDismiss: () => void;
  durationMs?: number;
  className?: string;
}

const KIND_CLASSES: Record<FlashKind, string> = {
  success:
    'border-[var(--color-arqel-success)]/60 bg-[var(--color-arqel-success)]/15 text-[var(--color-arqel-fg)]',
  error:
    'border-[var(--color-arqel-destructive)]/60 bg-[var(--color-arqel-destructive)]/15 text-[var(--color-arqel-fg)]',
  warning:
    'border-[var(--color-arqel-warning)]/60 bg-[var(--color-arqel-warning)]/15 text-[var(--color-arqel-fg)]',
  info: 'border-[var(--color-arqel-border)] bg-[var(--color-arqel-muted)] text-[var(--color-arqel-fg)]',
};

const KIND_GLYPH: Record<FlashKind, string> = {
  success: '✓',
  error: '✗',
  warning: '!',
  info: 'i',
};

export function FlashToast({
  kind,
  message,
  onDismiss,
  durationMs = 5000,
  className,
}: FlashToastProps) {
  useEffect(() => {
    if (durationMs <= 0) return undefined;
    const id = window.setTimeout(onDismiss, durationMs);
    return () => window.clearTimeout(id);
  }, [durationMs, onDismiss]);

  return (
    <div
      role={kind === 'error' ? 'alert' : 'status'}
      aria-live={kind === 'error' ? 'assertive' : 'polite'}
      className={cn(
        'pointer-events-auto flex items-start gap-3 rounded-[var(--radius-arqel)] border px-4 py-3 shadow-md',
        'min-w-[18rem] max-w-md text-sm',
        KIND_CLASSES[kind],
        className,
      )}
    >
      <span aria-hidden="true" className="font-bold leading-5">
        {KIND_GLYPH[kind]}
      </span>
      <span className="flex-1">{message}</span>
      <button
        type="button"
        aria-label="Dismiss"
        onClick={onDismiss}
        className="text-[var(--color-arqel-muted-fg)] hover:text-[var(--color-arqel-fg)]"
      >
        ✕
      </button>
    </div>
  );
}

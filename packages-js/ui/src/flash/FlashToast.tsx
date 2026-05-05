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
  success: 'border-[var(--chart-2)]/60 bg-[var(--chart-2)]/15 text-foreground',
  error: 'border-destructive/60 bg-destructive/15 text-foreground',
  warning: 'border-[var(--chart-4)]/60 bg-[var(--chart-4)]/15 text-foreground',
  info: 'border-border bg-muted text-foreground',
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
        'pointer-events-auto flex items-start gap-3 rounded-md border px-4 py-3 shadow-md',
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
        className="text-muted-foreground hover:text-foreground"
      >
        ✕
      </button>
    </div>
  );
}

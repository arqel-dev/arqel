/**
 * `<FlashContainer>` — bridges `useFlash()` to a stack of toasts.
 *
 * Subscribes to Inertia shared `flash` and pushes a toast each time a
 * new message of any kind arrives. Toast key is the (kind, message)
 * pair; identical repeats inside the same shared-props snapshot don't
 * duplicate. Defaults to fixed-positioning at top-right; pass `position`
 * to relocate.
 */

import { type FlashKind, useFlash } from '@arqel/hooks';
import { useCallback, useState } from 'react';
import { cn } from '../utils/cn.js';
import { FlashToast } from './FlashToast.js';

interface ToastEntry {
  id: number;
  kind: FlashKind;
  message: string;
}

export type FlashPosition = 'top-right' | 'top-left' | 'bottom-right' | 'bottom-left';

export interface FlashContainerProps {
  position?: FlashPosition;
  durationMs?: number;
  className?: string;
}

const POSITION_CLASSES: Record<FlashPosition, string> = {
  'top-right': 'top-4 right-4 items-end',
  'top-left': 'top-4 left-4 items-start',
  'bottom-right': 'bottom-4 right-4 items-end',
  'bottom-left': 'bottom-4 left-4 items-start',
};

let nextId = 0;

export function FlashContainer({
  position = 'top-right',
  durationMs = 5000,
  className,
}: FlashContainerProps) {
  const [toasts, setToasts] = useState<ToastEntry[]>([]);

  const handleMessage = useCallback((kind: FlashKind, message: string) => {
    setToasts((prev) => [...prev, { id: ++nextId, kind, message }]);
  }, []);

  useFlash({ onMessage: handleMessage });

  const dismiss = (id: number) => setToasts((prev) => prev.filter((t) => t.id !== id));

  return (
    <div
      data-arqel-flash=""
      className={cn(
        'pointer-events-none fixed z-50 flex flex-col gap-2',
        POSITION_CLASSES[position],
        className,
      )}
    >
      {toasts.map((toast) => (
        <FlashToast
          key={toast.id}
          kind={toast.kind}
          message={toast.message}
          durationMs={durationMs}
          onDismiss={() => dismiss(toast.id)}
        />
      ))}
    </div>
  );
}

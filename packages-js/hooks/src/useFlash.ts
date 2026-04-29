/**
 * `useFlash` — read flash messages from Inertia shared props.
 *
 * Optional `onMessage` callback fires once per *new* message of each kind,
 * allowing toast-library integrations without leaking effects elsewhere.
 */

import type { FlashPayload, SharedProps } from '@arqel/types/inertia';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

export type FlashKind = 'success' | 'error' | 'info' | 'warning';

export interface UseFlashOptions {
  onMessage?: (kind: FlashKind, message: string) => void;
}

export type UseFlashResult = FlashPayload;

const KINDS: FlashKind[] = ['success', 'error', 'info', 'warning'];

const EMPTY_FLASH: FlashPayload = {
  success: null,
  error: null,
  info: null,
  warning: null,
};

export function useFlash(options: UseFlashOptions = {}): UseFlashResult {
  const page = usePage();
  const props = page.props as unknown as SharedProps;
  const flash = props.flash ?? EMPTY_FLASH;
  const seenRef = useRef<Record<FlashKind, string | null>>({
    success: null,
    error: null,
    info: null,
    warning: null,
  });

  const { onMessage } = options;

  useEffect(() => {
    if (!onMessage) return;
    for (const kind of KINDS) {
      const value = flash[kind];
      if (value && value !== seenRef.current[kind]) {
        seenRef.current[kind] = value;
        onMessage(kind, value);
      }
    }
  }, [flash, onMessage]);

  return flash;
}

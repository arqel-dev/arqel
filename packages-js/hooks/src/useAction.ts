/**
 * `useAction` — invoke an Action via Inertia router.
 *
 * Phase 1 scope: thin wrapper around `router.visit` that exposes
 * `processing` and a `progress` stub (always 0). HOOKS-004 will wire
 * real Inertia progress events.
 */

import type { ActionSchema } from '@arqel-dev/types/actions';
import type { FormDataConvertible } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';

export interface UseActionResult {
  invoke: (record: { id: string | number } | null, payload?: Record<string, unknown>) => void;
  processing: boolean;
  progress: number;
}

export function useAction(action: ActionSchema): UseActionResult {
  const [processing, setProcessing] = useState(false);

  const invoke = useCallback(
    (record: { id: string | number } | null, payload: Record<string, unknown> = {}) => {
      const url = action.url ?? `/arqel-dev/actions/${action.name}`;
      const method = action.method.toLowerCase() as 'get' | 'post' | 'put' | 'patch' | 'delete';
      const data = record ? { record_id: record.id, ...payload } : payload;

      setProcessing(true);
      router.visit(url, {
        method,
        data: data as Record<string, FormDataConvertible>,
        preserveScroll: true,
        onFinish: () => setProcessing(false),
      });
    },
    [action.name, action.url, action.method],
  );

  return { invoke, processing, progress: 0 };
}

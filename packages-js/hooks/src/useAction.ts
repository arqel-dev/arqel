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
      // The server emits a `url` for every dispatchable action — stock
      // verbs + bulk + custom callback actions all resolve through core's
      // authorised routes (see `Action::resolveStockUrl()`, #48/#231). A
      // missing `url` means the action is a misconfiguration (e.g. a custom
      // action serialised without a resource context); we must NOT silently
      // POST to the dead `/arqel-dev/actions/{name}` route removed in #174.
      // Surface it clearly instead of masking it as a 404.
      if (!action.url) {
        throw new Error(
          `Arqel: action "${action.name}" has no url to dispatch to. A custom ` +
            `action with ->action() must be declared on the Resource's actions() ` +
            `so the server can emit its endpoint URL; otherwise give it ->url().`,
        );
      }
      const url = action.url;
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
